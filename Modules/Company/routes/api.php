<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Controllers\Api\BranchController;
use Modules\Company\Http\Controllers\Api\DivisionController;
use Modules\Company\Http\Controllers\Api\NotificationTemplateController;
use Modules\Company\Http\Controllers\Api\PositionController;
use Modules\Company\Http\Controllers\Api\ProjectClassController;
use Modules\Company\Http\Controllers\Api\RegionController;
use Modules\Company\Http\Controllers\Api\SettingController;
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

Route::middleware(['auth.session'])->prefix('v1')->group(function () {
    Route::apiResource('company', CompanyController::class)->names('company');
});

Route::middleware(['auth.session'])
    ->name('company.')
    ->group(function () {
        Route::get('branch/all', [BranchController::class, 'getAll'])->name('branches.get-all');
        Route::apiResource('branch', BranchController::class)->names('branches');
        Route::post('branch/bulk', [BranchController::class, 'bulkDelete'])->name('branches.bulk-delete');
        Route::get('inboxData', [CompanyController::class, 'loadInboxData'])->name('inboxData');
        Route::get('inboxData/clear', [CompanyController::class, 'clearInboxData']);
        Route::post('company/guidance', [CompanyController::class, 'uploadGuidance']);
        Route::get('company/guidance', [CompanyController::class, 'listUserGuides']);
        Route::delete('company/guidance/{guideId}', [CompanyController::class, 'deleteUserGuide']);
    });

Route::controller(PositionController::class)
    ->middleware(['auth.session'])
    ->group(function () {
        Route::get('positions', 'list');
        Route::get('positions/all', 'getAll');
        Route::get('positions/{uid}', 'show');
        Route::post('positions', 'store');
        Route::put('positions/{uid}', 'update');
        Route::delete('positions/{uid}', 'delete');
        Route::post('positions/bulk', 'bulkDelete');
    });

Route::controller(DivisionController::class)
    ->middleware(['auth.session'])
    ->group(function () {
        Route::get('divisions', 'list');
        Route::get('divisions/all', 'allDivisions');
        Route::get('divisions/{uid}', 'show');
        Route::post('divisions', 'store');
        Route::put('divisions/{uid}', 'update');
        Route::delete('divisions/{uid}', 'delete');
        Route::post('divisions/bulk', 'bulkDelete');
    });

Route::get('company/logs', [SettingController::class, 'getLogs']);

Route::get('setting/calculation', [SettingController::class, 'getPriceCalculation']);
Route::get('setting/{code?}', [SettingController::class, 'getSetting']);
Route::post('setting/{code}', [SettingController::class, 'storeSetting']);
Route::get('setting/{code}/{key}', [SettingController::class, 'getSettingByKeyAndCode']);

Route::get('world/countries', [RegionController::class, 'getCountries']);
Route::get('world/countries/selection', [RegionController::class, 'requestCountriesSelectionList']);
Route::get('world/countries/pagination', [RegionController::class, 'paginationCountries']);
Route::post('world/countries', [RegionController::class, 'storeCountry'])->name('storeCountry');
Route::put('world/countries/{countryId}', [RegionController::class, 'updateCountry'])->name('updateCountry');
Route::delete('world/countries/{countryId}', [RegionController::class, 'deleteCountry'])->name('deleteCountry');
Route::get('world/states/selection', [RegionController::class, 'requestStatesSelectionList']);
Route::get('world/states/pagination', [RegionController::class, 'paginationStates']);
Route::get('world/states', [RegionController::class, 'getStates']);
Route::post('world/states', [RegionController::class, 'storeState'])->name('storeState');
Route::put('world/states/{stateId}', [RegionController::class, 'updateState'])->name('updateState');
Route::delete('world/states/{stateId}', [RegionController::class, 'deleteState'])->name('deleteState');
Route::post('world/cities', [RegionController::class, 'storeCity'])->name('storeCity');
Route::put('world/cities/{cityId}', [RegionController::class, 'updateCity'])->name('updateCity');
Route::delete('world/cities/{cityId}', [RegionController::class, 'deleteCity'])->name('deleteCity');
Route::get('world/cities/pagination', [RegionController::class, 'paginationCities']);
Route::get('world/cities', [RegionController::class, 'getCities']);

// project class
Route::get('projectClass/getAll', [ProjectClassController::class, 'getAll']);
Route::put('projectClass/{projectClassId}/status', [ProjectClassController::class, 'updateStatus']);
Route::resource('projectClass', ProjectClassController::class);
Route::post('projectClass/bulk', [ProjectClassController::class, 'bulkDelete']);

Route::middleware(['auth.session'])
    ->group(function () {
        Route::get('religions', [CompanyController::class, 'getReligions']);
        Route::get('genders', [CompanyController::class, 'getGenders']);
        Route::get('banks', [CompanyController::class, 'getBanks']);
        Route::get('martial-status', [CompanyController::class, 'getMartialStatus']);
        Route::get('blood-type', [CompanyController::class, 'getBloodType']);
        Route::get('level-staff', [CompanyController::class, 'getLevelStaff']);
        Route::get('salary-type', [CompanyController::class, 'getSalaryType']);
        Route::get('salary-configuration', [CompanyController::class, 'getSalaryConfiguration']);
        Route::get('ptkp-type', [CompanyController::class, 'getPtkpType']);
        Route::get('relation-family', [CompanyController::class, 'getRelationFamily']);
        Route::get('tax-configuration', [CompanyController::class, 'getTaxConfiguration']);
        Route::get('employee-tax-status', [CompanyController::class, 'getEmployeeTaxStatus']);
        Route::get('jht-configuration', [CompanyController::class, 'getJhtConfiguration']);
        Route::get('overtime-status', [CompanyController::class, 'getOvertimeStatus']);
        Route::get('bpjs-kesehatan-config', [CompanyController::class, 'getBpjsKesehatanConfig']);
        Route::get('all-configuration', [CompanyController::class, 'getAllConfiguration']);
    });

/*
 * Notification template management (notification_settings table).
 * The static `metadata` and `preview` routes are declared before `{id}` so they are
 * not captured by the wildcard show route.
 */
Route::middleware(['auth.session'])
    ->prefix('v1')
    ->name('notification-templates.')
    ->group(function () {
        Route::get('notification-templates/metadata', [NotificationTemplateController::class, 'metadata'])->name('metadata');
        Route::post('notification-templates/preview', [NotificationTemplateController::class, 'preview'])->name('preview');
        Route::get('notification-templates', [NotificationTemplateController::class, 'index'])->name('index');
        Route::post('notification-templates', [NotificationTemplateController::class, 'store'])->name('store');
        Route::get('notification-templates/{id}', [NotificationTemplateController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('notification-templates/{id}', [NotificationTemplateController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('notification-templates/{id}', [NotificationTemplateController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });
