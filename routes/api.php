<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InteractiveController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TestingController;
use App\Http\Controllers\Api\UserController;
use App\Services\EncryptionService;
use App\Services\WhatsappService;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use KodePandai\Indonesia\Models\District;
use Modules\Finance\Jobs\InvoiceHasBeenDeletedJob;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/onesignal', function (Request $request) {
    logging('onesignal', $request->toArray());
});
Route::post('/onesignal-clicked', function (Request $request) {
    logging('onesignal-clicked', $request->toArray());
});

Route::post('interactive/image', [InteractiveController::class, 'generateImageQrCode']);

Route::get('testing', function () {
    $file = \Illuminate\Support\Facades\Storage::disk('public')->size('settings/image_17485858746.webp');

    return $file;
});

Route::get('telegram-login', [\Modules\Telegram\Http\Controllers\TelegramAuthorizationController::class, 'index']);

Route::get('line-flex', function () {});

Route::post('{token}/telegram-webhook', function (Request $request, string $token) {
    $event = new \Modules\Telegram\Service\Webhook\Telegram;
    $event->categorize($request->all());
});

Route::get('telegram', function () {
    $model = \Modules\Production\Models\Project::find(232);
    $observer = new \Modules\Production\Observers\NasFolderObserver;
    $observer->updated($model);
});

Route::get('messages', function () {
    $invoice = 'https://quicklyevents.com/storage/invoices/1/1706684868139-invoice.pdf';

    $payload = [
        'url' => $invoice,
    ];

    $service = new WhatsappService;
    $service->sendTemplateMessage('booking_confirmation_message_new', $payload, ['6285795327357']);
});

Route::post('base64', function (Request $request) {
    $base64Image = $request->image;
    // Decode the base64 string
    $imageParts = explode(';base64,', $base64Image);
    if (count($imageParts) != 2) {
        return response()->json([
            'error' => 'Is not base64 image',
        ]);
    }

    $imageTypeAux = explode('image/', $imageParts[0]);
    if (count($imageTypeAux) != 2) {
        return response()->json([
            'error' => 'Is not base64 image',
        ]);
    }

    $imageType = $imageTypeAux[1]; // e.g., png, jpg, etc.
    $imageBase64 = base64_decode($imageParts[1]);

    // Create a unique file name
    $fileName = uniqid().'.'.$imageType;

    // Define the storage path
    $filePath = 'base64/'.$fileName;

    // Save the image using Laravel's Storage facade
    \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $imageBase64);

    return response()->json([
        'success' => 'Upload success',
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

Route::get('notification/readAll', [\App\Http\Controllers\Api\NotificationController::class, 'readAll'])->middleware('auth:sanctum');
Route::get('notification/markAsRead/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])->middleware('auth:sanctum');

// Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::get('nasTestConnection', function (Request $request) {
    try {
        $data = $request->all();
        $http = 'http://'.$data['server'].':5000/webapi';
        $login = Http::get($http.'/auth.cgi', [
            'api' => 'SYNO.API.Auth',
            'version' => '3',
            'method' => 'login',
            'account' => $data['user'],
            'passwd' => $data['password'],
            'session' => 'FileStation',
            'format' => 'sid',
        ]);

        $login = json_decode($login->body(), true);

        if ($login['success'] == false) {
            return errorResponse('Account is not valid');
        }

        // get the folder detail
        $folder = HTTP::get($http.'/entry.cgi', [
            'api' => 'SYNO.FileStation.List',
            'version' => '2',
            'method' => 'list',
            'folder_path' => $data['folder'],
            '_sid' => $login['data']['sid'],
        ]);

        $response = json_decode($folder->body(), true);

        if ($response['success'] == false) {
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
    Route::post('userChangePassword/{userUid}', [LoginController::class, 'userChangePassword']);
});

Route::middleware('auth:sanctum')
    ->prefix('auth')
    ->group(function () {
        Route::post('logout', [LoginController::class, 'logout']);

    });

Route::get('users/activate/{key}', [UserController::class, 'activate']);

Route::middleware('auth:sanctum')
    ->group(function () {
        Route::get('logs', [DashboardController::class, 'getLogs']);
        Route::post('users/bulk', [UserController::class, 'bulkDelete'])->name('api.users.bulk-delete');
        Route::apiResource('users', UserController::class)->names('api.users');

        Route::post('roles/bulk', [RoleController::class, 'bulkDelete']);
        Route::get('roles/getAll', [RoleController::class, 'getAll']);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions/getAll', [PermissionController::class, 'getAll']);
        Route::apiResource('permissions', PermissionController::class);

        Route::apiResource('menus', MenuController::class);

        Route::get('dashboard/projectCalendar', [DashboardController::class, 'getProjectCalendar']);
        Route::get('dashboard/projectDeadline', [DashboardController::class, 'getProjectDeadline']);
        Route::get('dashboard/projectSong', [DashboardController::class, 'getProjectSong']);
        Route::get('dashboard/needCompleteProject', [DashboardController::class, 'needCompleteProject']);
        Route::get('dashboard/getReport', [DashboardController::class, 'getReport']);
        Route::get('dashboard/getVjWorkload', [DashboardController::class, 'getVjWorkload']);
        Route::get('dashboard/getEntertainmentSongWorkload', [DashboardController::class, 'getEntertainmentSongWorkload']);
        Route::get('dashboard/projectDifference', [DashboardController::class, 'getProjectDifference']);
        Route::get('dashboard/eventSuccessRate', [DashboardController::class, 'getEventSuccessRate']);
        Route::get('dashboard/getSalesPreview', [DashboardController::class, 'getSalesPreview']);

        // Dashboard for human resources
        Route::get('dashboard/hr/{type}', [DashboardController::class, 'getHrReport']);

        // NOTIFICATION
        Route::get('user/notifications', function () {
            $user = Auth::user();

            $notifications = $user->unreadNotifications;
            $notifications = $notifications->map(function ($item) {
                $item['created_at_raw'] = date('d F Y H:i', strtotime($item->created_at));

                return $item;
            });
            $service = new EncryptionService();
            $encrypt = $service->encrypt(json_encode($notifications), config('app.salt_key_encryption'));

            return apiResponse(
                generalResponse(
                    message: 'success',
                    data: [
                        'data' => $encrypt
                    ]
                )
            );
        });
    });

Route::post('line-webhook', [\Modules\LineMessaging\Http\Controllers\Api\LineController::class, 'webhook']);

Route::get('line-message', function () {
    $lineId = request()->get('line_id');

    $service = new \Modules\LineMessaging\Services\LineConnectionService;

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

    $service = new \App\Services\EncryptionService;
    $encrypt = $service->encrypt($user->email, env('SALT_KEY'));

    return (new \Modules\Hrd\Notifications\UserEmailActivation($user, $encrypt))
        ->toMail($user);

    // Notification::send($user, new \Modules\Hrd\Notifications\UserEmailActivation('passwordnya', $user));
});

Route::get('playground', function () {
    $user = Auth::user();
    InvoiceHasBeenDeletedJob::dispatch(parentNumber: '#7773', projectName: "Project Name", user: $user);
})->middleware('auth:sanctum');
