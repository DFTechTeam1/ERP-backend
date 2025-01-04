<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Controllers\Api\BranchController;
use Modules\Company\Http\Controllers\Api\ProjectClassController;
use Modules\Company\Http\Controllers\CompanyController;

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

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('company', CompanyController::class)->names('company');
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('branch', BranchController::class)->names('branch');
    Route::post('branch/bulk', [BranchController::class, 'bulkDelete']);
});

Route::controller(\Modules\Company\Http\Controllers\Api\PositionController::class)
    ->middleware(['auth:sanctum'])
    ->group(function () {
    Route::get('positions', 'list');
    Route::get('positions/all', 'getAll');
    Route::get('positions/{uid}', 'show');
    Route::post('positions', 'store');
    Route::put('positions/{uid}', 'update');
    Route::delete('positions/{uid}', 'delete');
    Route::post('positions/bulk', 'bulkDelete');
});

Route::controller(\Modules\Company\Http\Controllers\Api\DivisionController::class)
    ->middleware(['auth:sanctum'])
    ->group(function () {
   Route::get('divisions','list');
   Route::get('divisions/all', 'allDivisions');
   Route::get('divisions/{uid}','show');
   Route::post('divisions','store');
   Route::put('divisions/{uid}','update');
   Route::delete('divisions/{uid}','delete');
   Route::post('divisions/bulk','bulkDelete');
});

Route::get('setting/{code?}', [\Modules\Company\Http\Controllers\Api\SettingController::class, 'getSetting']);
Route::post('setting/{code}', [\Modules\Company\Http\Controllers\Api\SettingController::class, 'storeSetting']);
Route::get('setting/{code}/{key}', [\Modules\Company\Http\Controllers\Api\SettingController::class, 'getSettingByKeyAndCode']);

Route::get('world/countries', [\Modules\Company\Http\Controllers\Api\RegionController::class, 'getCountries']);
Route::get('world/states', [\Modules\Company\Http\Controllers\Api\RegionController::class, 'getStates']);
Route::get('world/cities', [\Modules\Company\Http\Controllers\Api\RegionController::class, 'getCities']);

// project class
Route::get('projectClass/getAll', [ProjectClassController::class, 'getAll']);
Route::resource('projectClass', ProjectClassController::class);
Route::post('projectClass/bulk', [ProjectClassController::class, 'bulkDelete']);

Route::middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('religions', [CompanyController::class, 'getReligions']);
        Route::get('genders', [CompanyController::class, 'getGenders']);
        Route::get('martial-status', [CompanyController::class, 'getMartialStatus']);
        Route::get('blood-type', [CompanyController::class, 'getBloodType']);
    });