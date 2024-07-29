<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;

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

$namespace = 'Modules\Inventory\Http\Controllers\Api';

Route::middleware(['auth:sanctum'])->group(function () use ($namespace) {
    Route::post('inventories/bulk', "{$namespace}\InventoryController@bulkDelete");
    Route::get('inventories/getAll', "{$namespace}\InventoryController@getAll");
    Route::apiResource('inventories', "{$namespace}\InventoryController")->names('inventories');
    Route::get('inventories/addStock/{uid}', "{$namespace}\InventoryController@addStock");
    Route::get('inventories/{uid}/items', "{$namespace}\InventoryController@itemList");

    Route::get('request-equipments', "{$namespace}\InventoryController@requestEquipmentList");

    Route::post('suppliers/bulk', "{$namespace}\SupplierController@bulkDelete");
    Route::get('suppliers/all', "{$namespace}\SupplierController@allList");
    Route::apiResource('suppliers', "{$namespace}\SupplierController");

    Route::post('brands/bulk', "{$namespace}\BrandController@bulkDelete");
    Route::get('brands/all', "{$namespace}\BrandController@allList");
    Route::apiResource('brands', "{$namespace}\BrandController");

    Route::post('units/bulk', "{$namespace}\UnitController@bulkDelete");
    Route::get('units/all', "{$namespace}\UnitController@allList");
    Route::apiResource('units', "{$namespace}\UnitController");

    Route::post('inventory-types/bulk', "{$namespace}\InventoryTypeController@bulkDelete");
    Route::get('inventory-types/all', "{$namespace}\InventoryTypeController@allList");
    Route::apiResource('inventory-types', "{$namespace}\InventoryTypeController");

    Route::get('custom-inventories', "{$namespace}\CustomInventoryController@index");
    Route::post('custom-inventories', "{$namespace}\CustomInventoryController@store");
    Route::put('custom-inventories/{uid}', "{$namespace}\CustomInventoryController@update");
    Route::get('custom-inventories/itemList', "{$namespace}\CustomInventoryController@getItemList");
    Route::get('custom-inventories/edit/{uid}', "{$namespace}\CustomInventoryController@show");
});
