<?php

use Illuminate\Support\Facades\Route;

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

Route::post('addon/askDeveloper', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'askDeveloper']);

Route::get('addon/f/updates', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'getUpdatedAddons']);
Route::get('addon', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'index']);
Route::get('addon/download/{id}/{type}', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'download']);
Route::get('addon/f/getAll', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'getAll']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('addon/nas', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'store']);
    Route::get('addon/{id}', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'show']);
    Route::post('addon/bulk', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'bulkDelete']);

    Route::post('addon/upgrades/{id}', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'upgrades']);
    Route::get('addon/nas/validate', [\Modules\Addon\Http\Controllers\Api\AddonController::class, 'validate']);
});
