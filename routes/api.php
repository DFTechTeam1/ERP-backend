<?php

use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Services\NasConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use KodePandai\Indonesia\Models\District;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('ilham', function () {
    $nas = new NasConnectionService();
    $setting = $nas->folderList();
    return response()->json($setting);
});

Route::prefix('auth')->group(function () {
    Route::post('login', 'Auth\LoginController@login');
});

Route::middleware('auth:sanctum')
    ->prefix('auth')
    ->group(function () {
        Route::post('logout', 'Auth\LoginController@logout');

    });
    
Route::middleware('auth:sanctum')
    ->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('users/activate/{key}', [UserController::class, 'activate']);

        Route::post('roles/bulk', [RoleController::class, 'bulkDelete']);
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