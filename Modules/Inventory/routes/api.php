<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\Api\InventoryController;

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
    Route::get('inventories/list-request-equipment', [InventoryController::class, 'getEquipmentForProjectRequest']);

    Route::post('inventories/export', "{$namespace}\InventoryController@export");
    Route::post('inventories/bulk', "{$namespace}\InventoryController@bulkDelete");
    Route::post('inventories/import', "{$namespace}\InventoryController@import");
    Route::get('inventories/getAll', "{$namespace}\InventoryController@getAll");
    Route::apiResource('inventories', "{$namespace}\InventoryController")->names('inventories');
    Route::get('inventories/addStock/{uid}', "{$namespace}\InventoryController@addStock");
    Route::get('inventories/{uid}/items', "{$namespace}\InventoryController@itemList");

    Route::get('request-equipments', "{$namespace}\InventoryController@requestEquipmentList");

    Route::post('suppliers/bulk', "{$namespace}\SupplierController@bulkDelete");
    Route::post('suppliers/import', "{$namespace}\SupplierController@import");
    Route::get('suppliers/all', "{$namespace}\SupplierController@allList");
    Route::apiResource('suppliers', "{$namespace}\SupplierController");

    Route::post('brands/bulk', "{$namespace}\BrandController@bulkDelete");
    Route::post('brands/import', "{$namespace}\BrandController@import");
    Route::get('brands/all', "{$namespace}\BrandController@allList");
    Route::apiResource('brands', "{$namespace}\BrandController");

    Route::post('units/bulk', "{$namespace}\UnitController@bulkDelete");
    Route::post('units/import', "{$namespace}\UnitController@import");
    Route::get('units/all', "{$namespace}\UnitController@allList");
    Route::apiResource('units', "{$namespace}\UnitController");

    Route::post('inventory-types/bulk', "{$namespace}\InventoryTypeController@bulkDelete");
    Route::post('inventory-types/import', "{$namespace}\InventoryTypeController@import");
    Route::get('inventory-types/all', "{$namespace}\InventoryTypeController@allList");
    Route::apiResource('inventory-types', "{$namespace}\InventoryTypeController");

    Route::get('custom-inventories', "{$namespace}\CustomInventoryController@index");
    Route::get('custom-inventories/get-assembled', "{$namespace}\CustomInventoryController@getAssembled");
    Route::post('custom-inventories', "{$namespace}\CustomInventoryController@store");
    Route::put('custom-inventories/{uid}', "{$namespace}\CustomInventoryController@update");
    Route::post('custom-inventories/bulk', "{$namespace}\CustomInventoryController@bulkDelete");
    Route::get('custom-inventories/itemList', "{$namespace}\CustomInventoryController@getItemList");
    Route::get('custom-inventories/edit/{uid}', "{$namespace}\CustomInventoryController@show");

    Route::get('request-inventory/approval-line', "{$namespace}\RequestInventoryController@getApprovalLines");
    Route::post('request-inventory/bulk', "{$namespace}\RequestInventoryController@bulkDelete");
    Route::get('request-inventory/get-request-inventory-status', "{$namespace}\RequestInventoryController@getRequestInventoryStatus");
    Route::post('request-inventory/closed', "{$namespace}\RequestInventoryController@closedRequest");
    Route::apiResource('request-inventory', "{$namespace}\RequestInventoryController");
    Route::post('request-inventory/convert-to-inventory/{uid}', "{$namespace}\RequestInventoryController@convertToInventory");
    Route::get('request-inventory/{type}/{uid}', "{$namespace}\RequestInventoryController@processRequest");

    Route::apiResource('user-inventory', "{$namespace}\UserInventoryController");
    Route::post('user-inventory/delete-inventory', "{$namespace}\UserInventoryController@deleteInventory");
    Route::post('user-inventory/add-item/{uid}', "{$namespace}\UserInventoryController@addItem");
    Route::get('user-inventory/available-inventories/{employeeUid}', "{$namespace}\UserInventoryController@getAvailableInventories");
    Route::get('user-inventory/available-custom-inventory/{employeeUid}', "{$namespace}\UserInventoryController@getAvailableCustomInventories");
    Route::get('user-inventory/get-user-information/{employeeUid}', "{$namespace}\UserInventoryController@getUserInformation");

});

Route::get('download/template/brand', "{$namespace}\InventoryController@downloadBrandTemplate");
Route::get('download/template/inventory', "{$namespace}\InventoryController@downloadInventoryTemplate");
Route::get('download/template/supplier', "{$namespace}\InventoryController@downloadSupplierTemplate");
Route::get('download/template/unit', "{$namespace}\InventoryController@downloadUnitTemplate");
Route::get('download/template/inventoryType', "{$namespace}\InventoryController@downloadInventoryTypeTemplate");