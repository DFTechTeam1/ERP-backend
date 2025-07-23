<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\Api\FinanceController as ApiFinanceController;
use Modules\Finance\Http\Controllers\Api\InvoiceController;
use Modules\Finance\Http\Controllers\FinanceController;

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

Route::middleware(['auth:sanctum'])->prefix('finance')->group(function () {
    Route::post('transaction/{projectDealUid}', [ApiFinanceController::class, 'createTransaction']);
    Route::post('invoices/download', [ApiFinanceController::class, 'downloadInvoice']);

    // manage invoice
    Route::prefix('{projectDealUid}')->group(function () {
        Route::post('/billInvoice', [InvoiceController::class, 'generateBillInvoice']);
        Route::post('invoices/temporary', [InvoiceController::class, 'updateTemporaryData'])->name('invoices.updateTemporaryData');
        Route::resource('invoices', InvoiceController::class);

        // transaction
        Route::post('transaction', [InvoiceController::class, 'createTransaction']);
    });

});