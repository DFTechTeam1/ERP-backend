<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\FinanceController;
use Modules\Inventory\Http\Controllers\Api\InventoryController;

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
    Route::resource('finance', FinanceController::class)->names('finance');

    Route::get('finance/download/export/financeReport', [FinanceController::class, 'downloadFinanceReport'])->name('finance.download.export.financeReport');
    Route::get('download/export/inventoryReport', [InventoryController::class, 'downloadInventoryReport'])->name('inventory.download.export.inventoryReport')->middleware('signed');
});
