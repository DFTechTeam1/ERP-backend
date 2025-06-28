<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\Api\FinanceController as ApiFinanceController;
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
    Route::post('transaction/{quotationId}/{projectDealUid}', [ApiFinanceController::class, 'createTransaction']);
});