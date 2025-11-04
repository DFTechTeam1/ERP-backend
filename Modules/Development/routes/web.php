<?php

use Illuminate\Support\Facades\Route;
use Modules\Development\Http\Controllers\DevelopmentController;

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
    Route::resource('development', DevelopmentController::class)->names('development');
});

Route::get('development/project/{taskUid}/downloadAttachment/{attachmentId}', [DevelopmentController::class, 'downloadAttachment'])->name('development.project.downloadAttachment');
