<?php

use Illuminate\Support\Facades\Route;
use Modules\Nas\Http\Controllers\NasController;

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

Route::middleware(['auth:sanctum'])->prefix('nas')->group(function () {
    Route::post('testConnection', [NasController::class, 'testConnection']);
    Route::post('addon/storeConfiguration', [NasController::class, 'storeAddonConfiguration']);
    Route::get('addon/configuration', [NasController::class, 'addonConfiguration']);
    Route::post('login', [NasController::class, 'login']);
    Route::get('folderList', [NasController::class, 'folderList']);

    // show addons
});
Route::get('addons', [NasController::class, 'showAddons']);
