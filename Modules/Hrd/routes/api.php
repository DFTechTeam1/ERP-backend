<?php

use Illuminate\Support\Facades\Route;
use Modules\Hrd\Http\Controllers\HrdController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('hrd', HrdController::class)->names('hrd');
});

Route::controller(\Modules\Hrd\Http\Controllers\Api\EmployeeController::class)
    ->group(function () {
        Route::get('employees/activate/{key}', 'activateAccount');
    });

Route::controller(\Modules\Hrd\Http\Controllers\Api\EmployeeController::class)
    ->middleware(['auth:sanctum'])
    ->group(function() {
        Route::get('employees','list');
        Route::get('employees/checkEmail', 'checkEmail');
        Route::get('employees/checkIdNumber', 'checkIdNumber');
        Route::get('employees/generateEmployeeId', 'generateEmployeeID');
        Route::get('employees/all','getAll');
        Route::get('employees/{uid}','show');
        Route::post('employees','store');
        Route::put('employees/{uid}','update');
        Route::delete('employees/{uid}','delete');
        Route::post('employees/bulk', "bulkDelete");
        Route::post('employees/addAsUser', 'addAsUser');
    });
