<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TestingController;
use App\Http\Controllers\Api\UserController;
use App\Services\Telegram\TelegramService;
use App\Services\WhatsappService;
use App\Services\LocalNasService;
use App\Services\NasConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use KodePandai\Indonesia\Models\District;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Broadcast;
use Modules\Inventory\Jobs\NewRequestInventoryJob;
use Modules\Inventory\Services\UserInventoryService;
use Modules\Production\Jobs\AssignTaskJob;
use Modules\Telegram\Http\Controllers\TelegramAuthorizationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('interactive/image/{deviceId}', function (Request $request, $deviceId) {
    try {
        $date = date('Y-m-d');
        $filepath = "interactive/qr/{$deviceId}/{$date}";

        if (!is_dir(storage_path('app/public/' . $filepath))) {
            mkdir(storage_path('app/public/' . $filepath), 0777, true);
        }

        $filename = date('YmdHis') . '.png';
        $image = uploadBase64($request->getContent(), $filepath);
        if ($image) {
            // create qr
            $qrcode = generateQrcode(env('APP_URL') . '/interactive/download?file=' . $image . '&d=' . $deviceId, $filepath . '/' . $filename);
        }
        return $qrcode ? 'data:image/png;base64,' . base64_encode(file_get_contents(storage_path("app/public/{$qrcode}"))) : '';
    } catch (\Throwable $th) {
        return json_encode([
            'error' => $th->getMessage()
        ]);
    }
});

Route::get('testing', function () {
    $items = \Modules\Inventory\Models\UserInventoryMaster::with('items:id,user_inventory_master_id,inventory_id,quantity')
        ->latest()->first();

    $inventories = collect($items->items)->map(function ($item) {
        return [
            'id' => $item->inventory_id,
            'quantity' => $item->quantity,
            'user_inventory_master_id' => $item->user_inventory_master_id,
        ];
    })->toArray();

    $new = [
        'id' => 1,
        'quantity' => 10,
        'user_inventory_master_id' => 0,
    ];

    $inventories = collect($inventories)->push($new);

    $service = new UserInventoryService();
    return $service->addItem($inventories->toArray(), $items);
});

Route::get('telegram-login', [\Modules\Telegram\Http\Controllers\TelegramAuthorizationController::class, 'index']);


Route::get('line-flex', function () {

});

Route::post('{token}/telegram-webhook', function (Request $request, string $token) {
    $event = new \Modules\Telegram\Service\Webhook\Telegram();
    $event->categorize($request->all());
});

Route::get('messages', function () {
   $invoice = 'https://quicklyevents.com/storage/invoices/1/1706684868139-invoice.pdf';

   $payload = [
       'url' => $invoice
   ];

    $service = new WhatsappService();
    $service->sendTemplateMessage('booking_confirmation_message_new', $payload, ['6285795327357']);
});

Route::post('base64', function (Request $request) {
    $base64Image = $request->image;
    // Decode the base64 string
    $imageParts = explode(";base64,", $base64Image);
    if (count($imageParts) != 2) {
        return response()->json([
            'error' => 'Is not base64 image'
        ]);
    }

    $imageTypeAux = explode("image/", $imageParts[0]);
    if (count($imageTypeAux) != 2) {
        return response()->json([
            'error' => 'Is not base64 image'
        ]);
    }

    $imageType = $imageTypeAux[1]; // e.g., png, jpg, etc.
    $imageBase64 = base64_decode($imageParts[1]);

    // Create a unique file name
    $fileName = uniqid() . '.' . $imageType;

    // Define the storage path
    $filePath = 'base64/' . $fileName;

    // Save the image using Laravel's Storage facade
    \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $imageBase64);

    return response()->json([
        'success' => 'Upload success'
    ]);
});

Route::get('detail-migrate/{code}', [LoginController::class, 'getDetailFromMigrate']);

Route::get('forms', [TestingController::class, 'forms']);
Route::post('forms', [TestingController::class, 'storeForm']);
Route::get('forms/{uid}', [TestingController::class, 'detailForm']);
Route::put('forms/{uid}', [TestingController::class, 'updateForm']);
Route::delete('forms/{uid}', [TestingController::class, 'deleteForm']);
Route::post('forms/response/{uid}', [TestingController::class, 'storeFormResponse']);

Route::get('delete-projects', [\App\Http\Controllers\Api\TestingController::class, 'deleteCurrentProjects']);
Route::post('manual-migrate-project', [\App\Http\Controllers\Api\TestingController::class, 'manualMigrateProjects']);
Route::post('manual-assign-pm', [\App\Http\Controllers\Api\TestingController::class, 'manualAssignPM']);
Route::post('manual-assign-status', [\App\Http\Controllers\Api\TestingController::class, 'manualAssignStatus']);
Route::get('generate-official-email', [\App\Http\Controllers\Api\TestingController::class, 'generateOfficialEmail']);

Route::get('notification/markAsRead/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])->middleware('auth:sanctum');

// Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::get('nasTestConnection', function (Request $request) {
    try {
        $data = $request->all();
        $http = 'http://' . $data['server'] . ':5000/webapi';
        $login = Http::get($http . '/auth.cgi', [
            'api' => 'SYNO.API.Auth',
            'version' => '3',
            'method' => 'login',
            'account' => $data['user'],
            'passwd' => $data['password'],
            'session' => 'FileStation',
            'format' => 'sid',
        ]);

        $login = json_decode($login->body(), true);

        if ($login['success'] == FALSE) {
            return errorResponse('Account is not valid');
        }

        // get the folder detail
        $folder = HTTP::get($http . '/entry.cgi', [
            'api' => 'SYNO.FileStation.List',
            'version' => '2',
            'method' => 'list',
            'folder_path' => $data['folder'],
            '_sid' => $login['data']['sid'],
        ]);

        $response = json_decode($folder->body(), true);

        if ($response['success'] == FALSE) {
            return errorResponse('Cannot get folder information');
        }

        return generalResponse(
            __('global.connectionIsSecure'),
            false,
        );
    } catch (\Throwable $th) {
        return errorResponse('Cannot get to the given server');
    }
});

Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->name('login-form');
    Route::post('forgotPassword', [LoginController::class, 'forgotPassword']);
    Route::post('resetPassword', [LoginController::class, 'resetPassword']);
    Route::post('changePassword', [LoginController::class, 'changePassword']);
});

Route::middleware('auth:sanctum')
    ->prefix('auth')
    ->group(function () {
        Route::post('logout', [LoginController::class, 'logout']);

    });

Route::get('users/activate/{key}', [UserController::class, 'activate']);

Route::middleware('auth:sanctum')
    ->group(function () {
        Route::post('users/bulk', [UserController::class, 'bulkDelete']);
        Route::apiResource('users', UserController::class);

        Route::post('roles/bulk', [RoleController::class, 'bulkDelete']);
        Route::get('roles/getAll', [RoleController::class, 'getAll']);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions/getAll', [PermissionController::class, 'getAll']);
        Route::apiResource('permissions', PermissionController::class);

        Route::apiResource('menus', MenuController::class);

        Route::get('dashboard/projectCalendar', [DashboardController::class, 'getProjectCalendar']);
        Route::get('dashboard/projectDeadline', [DashboardController::class, 'getProjectDeadline']);
        Route::get('dashboard/getReport', [DashboardController::class, 'getReport']);
    });

Route::post('line-webhook', [\Modules\LineMessaging\Http\Controllers\Api\LineController::class, 'webhook']);

Route::get('line-message', function () {
    $lineId = request()->get('line_id');

    $service = new \Modules\LineMessaging\Services\LineConnectionService();

    $messages = [
        [
            'type' => 'text',
            'text' => 'Testing',
        ],
    ];

    return $service->sendMessage($messages, $lineId);
});

// override indoneia district API
Route::get('indonesia/districts', function () {
    $cityCode = request('city_code');
    $districts = District::selectRaw('TRIM(code) as value, name as title')
        ->where('city_code', $cityCode)
        ->get();

    return response()->json([
        'data' => $districts,
    ]);
});

// LOCAL NAS CONNECTION
Route::prefix('local')->group(function () {
    Route::get('sharedFolders', [\App\Http\Controllers\Api\Nas\LocalNasController::class, 'sharedFolders']);
    Route::post('upload', [\App\Http\Controllers\Api\Nas\LocalNasController::class, 'upload']);
});

Route::get('notification', function () {
    $user = \App\Models\User::selectRaw('*')->first();

    $service = new \App\Services\EncryptionService();
    $encrypt = $service->encrypt($user->email, env('SALT_KEY'));

    return (new \Modules\Hrd\Notifications\UserEmailActivation($user, $encrypt))
        ->toMail($user);

    // Notification::send($user, new \Modules\Hrd\Notifications\UserEmailActivation('passwordnya', $user));
});
