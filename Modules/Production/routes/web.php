<?php

use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\Api\InteractiveController;
use Modules\Production\Http\Controllers\ProductionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group([], function () {
    Route::resource('production', ProductionController::class)->names('production');
});

Route::prefix('production')->group(function () {
    Route::get('interactives/approve/{requestId}', [InteractiveController::class, 'approveInteractive'])->name('email.production.interactives.approve');
    Route::get('interactives/reject/{requestId}', [InteractiveController::class, 'rejectInteractiveRequest'])->name('email.production.interactives.reject');
    Route::get('interactives/project/{taskUid}/downloadAttachment/{attachmentId}', [InteractiveController::class, 'downloadAttachment'])->name('interactives.project.downloadAttachment');
});
