<?php

use App\Http\Middleware\CustomSignedRouteMiddleware;
use Illuminate\Http\Request;
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

Route::middleware(['auth:sanctum'])->group(function () {
    // route for price changes reasons
    Route::get('price-reasons', [ApiFinanceController::class, 'getPriceChangeReasons'])
        ->name('finance.getPriceChangeReasons');

    Route::prefix('finance')->group(function () {
        Route::post('transaction/{projectDealUid}', [ApiFinanceController::class, 'createTransaction']);
        Route::post('invoices/download', [ApiFinanceController::class, 'downloadInvoice']);
    
        // manage invoice
        Route::prefix('{projectDealUid}')->group(function () {
            Route::post('/billInvoice', [InvoiceController::class, 'generateBillInvoice']);
            Route::post('invoices/temporary', [InvoiceController::class, 'updateTemporaryData'])->name('invoices.updateTemporaryData');
            Route::resource('invoices', InvoiceController::class);
            Route::get('invoices/{invoiceUid}/approve/{pendingUpdateId}', [InvoiceController::class, 'approveChanges']);
            Route::get('invoices/{invoiceUid}/reject/{pendingUpdateId}', [InvoiceController::class, 'rejectChanges']);
    
            Route::post('price', [ApiFinanceController::class, 'requestPriceChanges'])
                ->name('finance.requestPriceChanges');
    
            // url for apprrove price changes
            Route::get('price/approve/{changeId}', [ApiFinanceController::class, 'approvePriceChanges'])
                ->name('finance.approvePriceChanges');
    
            // url for reject price changes
            Route::post('price/reject/{changeId}', [ApiFinanceController::class, 'rejectPriceChanges'])
                ->name('finance.rejectPriceChanges');
    
            // transaction
            Route::post('transaction', [InvoiceController::class, 'createTransaction']);
        });

    });

    Route::post('report/global', [ApiFinanceController::class, 'exportFinanceData']);
});

Route::get('finance/invoices/approve', [InvoiceController::class, 'emailApproveChanges'])
    ->name('invoices.approveChanges')
    ->middleware('customSignedMiddleware');
Route::get('finance/invoices/reject', [InvoiceController::class, 'emailRejectChanges'])
    ->name('invoices.rejectChanges')
    ->middleware('customSignedMiddleware');