<?php

use Illuminate\Support\Facades\Route;
use Modules\Email\Http\Controllers\Api\EmailController as ApiEmailController;
use Modules\Email\Http\Controllers\Api\SlackController;
use Modules\Email\Http\Controllers\EmailController;

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

Route::middleware(['auth.session'])->prefix('v1')->group(function () {
    Route::apiResource('email', EmailController::class)->names('email');
});

Route::middleware(['internal.service'])
    ->name('email')
    ->prefix('system-email')
    ->group(function () {
        Route::post('send/employee-mutation', [ApiEmailController::class, 'send']);
    });

Route::middleware(['internal.service'])
    ->name('slack')
    ->prefix('system-slack')
    ->group(function () {
        Route::post('send-message', [SlackController::class, 'send']);
    });
