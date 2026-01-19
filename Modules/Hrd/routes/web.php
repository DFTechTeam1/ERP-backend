<?php

use Illuminate\Support\Facades\Route;
use Modules\Hrd\Http\Controllers\HrdController;

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
    Route::resource('hrd', HrdController::class)->names('hrd');
});


Route::get('performanceReport/download/export', [\Modules\Hrd\Http\Controllers\Api\PerformanceReportController::class, 'downloadExport'])->name('hrd.download.export.performanceReport');