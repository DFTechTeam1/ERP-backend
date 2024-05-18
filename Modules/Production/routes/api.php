<?php

use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\Api\ProjectController;
use Modules\Production\Http\Controllers\ProductionController;

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

Route::middleware(['auth:sanctum'])->prefix('production')->group(function () {
    Route::get('eventTypes', [ProjectController::class, 'getEventTypes']);
    Route::get('classList', [ProjectController::class, 'getClassList']);
});
