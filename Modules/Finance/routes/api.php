<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\Api\FinanceController as ApiFinanceController;
use Modules\Finance\Http\Controllers\Api\FinanceDashboardController;
use Modules\Finance\Http\Controllers\Api\InvoiceChangeRequestController;
use Modules\Finance\Http\Controllers\Api\InvoiceController;
use Modules\Finance\Http\Controllers\Api\MarketingDashboardController;

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

Route::middleware(['auth.session'])->group(function () {
    // route for price changes reasons
    Route::get('price-reasons', [ApiFinanceController::class, 'getPriceChangeReasons'])
        ->name('finance.getPriceChangeReasons');

    Route::prefix('finance')->group(function () {
        Route::post('transaction/{projectDealUid}', [ApiFinanceController::class, 'createTransaction']);
        Route::get('transactions', [ApiFinanceController::class, 'index']);
        Route::get('transactions/summary', [ApiFinanceController::class, 'getTransactionSummary']);
        Route::get('transactions/trend/monthly', [ApiFinanceController::class, 'getMonthlyTrend']);
        Route::get('transactions/sources/top', [ApiFinanceController::class, 'getTopSources']);
        Route::get('transactions/outstanding', [ApiFinanceController::class, 'getOutstandingData']);
        Route::get('transactions/{uid}', [ApiFinanceController::class, 'show']);
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
            Route::post('price/approve/{changeId}', [ApiFinanceController::class, 'approvePriceChanges'])
                ->name('finance.approvePriceChanges');

            // url for reject price changes
            Route::post('price/reject/{changeId}', [ApiFinanceController::class, 'rejectPriceChanges'])
                ->name('finance.rejectPriceChanges');

            // transaction
            Route::post('transaction', [InvoiceController::class, 'createTransaction']);
        });

    });

    Route::post('finance/report/global', [ApiFinanceController::class, 'exportFinanceData']);

    // Sales dashboard - role-scoped. Sales users see own deals; Director/Root
    // see company-wide unless narrowed via `?sales_employee_id=`.
    Route::prefix('finance/marketing-dashboard')->group(function () {
        Route::get('pipeline', [MarketingDashboardController::class, 'pipeline'])
            ->name('finance.marketing.pipeline');
        Route::get('deals', [MarketingDashboardController::class, 'deals'])
            ->name('finance.marketing.deals');
        Route::get('sales-people', [MarketingDashboardController::class, 'salesPeople'])
            ->name('finance.marketing.salesPeople');
    });

    // Invoice change requests menu (Request → Invoice Changes).
    // Permissions gate each verb; list_invoice_changes gates the page load.
    Route::prefix('finance/invoice-changes')->group(function () {
        Route::get('', [InvoiceChangeRequestController::class, 'index'])
            ->middleware('can:list_invoice_changes')
            ->name('finance.invoiceChanges.index');
        Route::post('{id}/approve', [InvoiceChangeRequestController::class, 'approve'])
            ->whereNumber('id')
            ->middleware('can:approve_invoice_changes')
            ->name('finance.invoiceChanges.approve');
        Route::post('{id}/reject', [InvoiceChangeRequestController::class, 'reject'])
            ->whereNumber('id')
            ->middleware('can:reject_invoice_changes')
            ->name('finance.invoiceChanges.reject');
    });

    // Finance dashboard - pending queue endpoints (Finance / Director / Root).
    Route::prefix('finance/dashboard')->group(function () {
        Route::get('pending-invoice-updates', [FinanceDashboardController::class, 'pendingInvoiceUpdates'])
            ->name('finance.dashboard.pendingInvoiceUpdates');
        Route::get('pending-price-changes', [FinanceDashboardController::class, 'pendingPriceChanges'])
            ->name('finance.dashboard.pendingPriceChanges');
    });

    // Finance insight bundle - same handlers as the MCP versions, but session-auth
    // so the SPA can consume them. Role gating happens inside FinanceInsightService.
    Route::get('finance/insight', [ApiFinanceController::class, 'getFinanceInsight'])
        ->name('finance.insight');
    Route::get('finance/insight/receivables', [ApiFinanceController::class, 'getFinanceReceivables'])
        ->name('finance.insight.receivables');
    Route::get('finance/insight/marketing-performance', [ApiFinanceController::class, 'getMarketingPerformance'])
        ->name('finance.insight.marketingPerformance');
    Route::get('finance/insight/top-deals', [ApiFinanceController::class, 'getTopDeals'])
        ->name('finance.insight.topDeals');
});

Route::get('finance/invoices/approve', [InvoiceController::class, 'emailApproveChanges'])
    ->name('invoices.approveChanges')
    ->middleware('customSignedMiddleware');
Route::get('finance/invoices/reject', [InvoiceController::class, 'emailRejectChanges'])
    ->name('invoices.rejectChanges')
    ->middleware('customSignedMiddleware');
