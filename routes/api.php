<?php

use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Services\NasConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use KodePandai\Indonesia\Models\District;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('ilham', function () {
    $url = env('NAS_URL');

    // $folderList = Http::get($url);
    // $body = json_decode($folderList->body(), true);

    // return response()->json($body);

    $ch = curl_init($url . '/ilham');    // initialize curl handle
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_PROXY, "https://bright-huge-gopher.ngrok-free.app"); //your proxy url
    $data = curl_exec($ch);

    return response()->json($data);
});

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
    Route::post('login', 'Auth\LoginController@login');
});

Route::middleware('auth:sanctum')
    ->prefix('auth')
    ->group(function () {
        Route::post('logout', 'Auth\LoginController@logout');

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
    });

Route::post('line-webhook', function (Request $request) {
    Log::debug('Webhook line: ', json_encode($request->all()));

    return response()->json([
        'status' => 200,
        'message' => 'success',
    ], 200);
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

Route::get('notification', function () {
    $user = \App\Models\User::selectRaw('*')->first();

    $service = new \App\Services\EncryptionService();
    $encrypt = $service->encrypt($user->email, env('SALT_KEY'));

    return (new \Modules\Hrd\Notifications\UserEmailActivation($user, $encrypt))
        ->toMail($user);

    // Notification::send($user, new \Modules\Hrd\Notifications\UserEmailActivation('passwordnya', $user));
});