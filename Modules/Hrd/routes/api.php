<?php

use Illuminate\Support\Facades\Route;
use Modules\Hrd\Http\Controllers\HrdController;

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
    Route::apiResource('hrd', HrdController::class)->names('hrd');
});

Route::controller(\Modules\Hrd\Http\Controllers\Api\EmployeeController::class)
    ->group(function () {
        Route::get('employees/activate/{key}', 'activateAccount');
    });

Route::get('employees/downloadTemplate', [\Modules\Hrd\Http\Controllers\Api\EmployeeController::class, 'downloadTemplate']);

Route::controller(\Modules\Hrd\Http\Controllers\Api\EmployeeController::class)
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('employees', 'list');
        Route::post('employees', 'store')->name('employees.store');
        Route::get('employees/all', 'getAll');
        Route::get('employees/employmentChart', 'getEmploymentChart');
        Route::get('employees/dashboardElement', 'getDashboardElement');
        Route::post('employees/export', 'export');
        Route::get('employees/highesEventNumber', 'getTheHighestEventNumberInPic');
        Route::get('employees/checkEmail', 'checkEmail');
        Route::get('employees/checkIdNumber', 'checkIdNumber');
        Route::get('employees/generateEmployeeId', 'generateEmployeeID');
        Route::get('employees/getProjectManagers', 'getProjectManagers');
        Route::get('employees/getAllStatus', 'getAllStatus')->name('employees.getAllStatus');
        Route::get('employees/generateRandomPassword', 'generateRandomPassword')->name('employees.generateRandomPassword');
        Route::get('employees/{uid}', 'show')->name('hrd.employees.show');
        Route::put('employees/{uid}', 'update')->name('employees.update');
        Route::delete('employees/{uid}', 'delete');
        Route::post('employees/validateEmployeeId', 'validateEmployeeID');
        Route::post('employees/bulk', 'bulkDelete');
        Route::post('employees/addAsUser', 'addAsUser')->name('employees.addAsUser');
        Route::post('employees/submitImport', 'submitImport');
        Route::get('employees/getVJ/{projectUid}', 'getVJ');
        Route::post('employees/{employeeUid}/resign', 'resign');
        Route::get('employees/{employeeUid}/cancelResign', 'cancelResign');
        Route::put('employees/{uid}/basicInfo', 'updateBasicInfo')->name('employees.updateBasicInfo');
        Route::put('employees/{uid}/identity', 'updateIdentity')->name('employees.updateIdentity');
        Route::post('employees/{employeeUid}/storeFamily', 'storeFamily')->name('employees.storeFamily');
        Route::put('employees/{familyUid}/updateFamily', 'updateFamily')->name('employees.updateFamily');
        Route::get('employees/{employeeUid}/initFamily', 'initFamily');
        Route::delete('employees/{familyUid}/deleteFamily', 'deleteFamily');
        Route::post('employees/{employeeUid}/storeEmergency', 'storeEmergency');
        Route::put('employees/{emergencyUid}/updateEmergency', 'updateEmergency');
        Route::put('employees/{employeeUid}/updateEmployment', 'updateEmployment')->name('employees.updateEmployment');
        Route::get('employees/{employeeUid}/initEmergency', 'initEmergency');
        Route::delete('employees/{emergencyUid}/deleteEmergency', 'deleteEmergency');
        Route::get('employees/modeller/{projectUid?}/{taskUid?}', 'get3DModeller');

        Route::post('employees/import', 'import');
    });

Route::middleware('auth:sanctum')
    ->prefix('performanceReport')
    ->group(function () {
        Route::post('export', [\Modules\Hrd\Http\Controllers\Api\PerformanceReportController::class, 'export']);
        Route::get('/{employeeId}', [\Modules\Hrd\Http\Controllers\Api\PerformanceReportController::class, 'performanceDetail']);
        Route::get('getTeams', [\Modules\Hrd\Http\Controllers\Api\PerformanceReportController::class, 'getTeams']);
        Route::get('getMembers/{leaderId}', [\Modules\Hrd\Http\Controllers\Api\PerformanceReportController::class, 'getMembers']);
        Route::get('getMembers/filterMember', [\Modules\Hrd\Http\Controllers\Api\PerformanceReportController::class, 'filterMember']);
    });
