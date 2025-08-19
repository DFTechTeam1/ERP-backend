<?php

use Illuminate\Support\Facades\Route;
use Modules\Development\Http\Controllers\DevelopmentController;
use Modules\Development\Http\Controllers\Api\DevelopmentProjectController;

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

Route::middleware(['auth:sanctum'])->prefix('development')->group(function () {
    Route::get('/projects', [DevelopmentProjectController::class, 'index'])->name('development.projects.index');
    Route::post('/projects', [DevelopmentProjectController::class, 'store'])->name('development.projects.store');
    Route::get('/projects/{id}', [DevelopmentProjectController::class, 'show'])->name('development.projects.show');
    Route::put('/projects/{id}', [DevelopmentProjectController::class, 'update'])->name('development.projects.update');
    Route::delete('/projects/{id}', [DevelopmentProjectController::class, 'destroy'])->name('development.projects.destroy');
});
