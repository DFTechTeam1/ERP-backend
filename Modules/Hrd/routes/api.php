<?php

use Illuminate\Support\Facades\Route;
use Modules\Hrd\Http\Controllers\Api\EmployeeController;
use Modules\Hrd\Http\Controllers\Api\PerformanceReportController;
use Modules\Hrd\Http\Controllers\Api\PositionSyncController;
use Modules\Hrd\Http\Controllers\Api\SignatureController;
use Modules\Hrd\Http\Controllers\Api\WhatsappGroupController;
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

Route::middleware(['auth.session'])->prefix('v1')->group(function () {
    Route::apiResource('hrd', HrdController::class)->names('hrd');
});

Route::controller(EmployeeController::class)
    ->group(function () {
        Route::get('employees/activate/account', 'activateAccount')
            ->name('hrd.activate-account.nokey');
        Route::get('employees/activate/{key}', 'activateAccount')
            ->name('hrd.activate-account');
    });

Route::get('employees/downloadTemplate', [EmployeeController::class, 'downloadTemplate']);

// Route::middleware('partner')->group(function() {
//     Route::post('employees/{employeeId}/resendVerification', [EmployeeController::class, 'resendVerificationEmail'])->name('employees.resendVerificationEmail');
// });

Route::controller(EmployeeController::class)
    ->middleware(['auth.session'])
    ->group(function () {
        Route::get('employees', 'list');
        Route::post('employees', 'store')->name('employees.store');
        Route::get('employees/all', 'getAll');
        Route::get('employees/employmentChart', 'getEmploymentChart');
        Route::get('employees/dashboardElement', 'getDashboardElement');
        Route::get('employees/active-total', 'totalActiveEmployee');
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
        Route::post('employees/{employeeUid}/resign', 'resign')->name('employees.resign');
        Route::get('employees/{employeeUid}/cancelResign', 'cancelResign')->name('employees.cancelResign');
        Route::put('employees/{uid}/basicInfo', 'updateBasicInfo')->name('employees.updateBasicInfo');
        Route::put('employees/{uid}/identity', 'updateIdentity')->name('employees.updateIdentity');
        Route::put('employees/{employeeUid}/v2', 'updateEmployeeV2')->name('employees.updateEmployeeV2');
        Route::post('employees/{employeeUid}/storeFamily', 'storeFamily')->name('employees.storeFamily');
        Route::post('employee/resendVerification/{employeeId}', 'resendVerificationEmail');
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

        // Signature feature
        Route::prefix('signatures')
            ->group(function () {
                Route::get('document-types', [SignatureController::class, 'listDocumentTypes']);
                Route::post('document-types', [SignatureController::class, 'storeDocumentTypes']);
                Route::post('document-types/bulk', [SignatureController::class, 'bulkCreateDocumentType']);
                Route::put('document-types/bulk', [SignatureController::class, 'bulkEditDocumentType']);
                Route::delete('document-types/bulk', [SignatureController::class, 'bulkDeleteDocumentType']);
                Route::put('document-types/{documentId}', [SignatureController::class, 'updateDocumentType']);
                Route::post('document-types/detect-placeholder', [SignatureController::class, 'detectPlaceholder']);

                // Templates
                Route::post('templates', [SignatureController::class, 'createTemplate']);
                Route::get('templates', [SignatureController::class, 'listTemplates']);
                Route::delete('templates/{templateUid}', [SignatureController::class, 'deleteTemplate']);
                Route::post('templates/{documentUid}/approval', [SignatureController::class, 'approvalMasterDocument']);
                Route::post('templates/{templateUid}/generate', [SignatureController::class, 'generateDocument']);
                Route::post('documents/disburse', [SignatureController::class, 'bulkGenerateDocument']);

                Route::get('signatories', [SignatureController::class, 'listSignatories']);
                Route::post('signatories/assign/{mappingUid}', [SignatureController::class, 'assignSignatories']);

                // Employee's own saved signatures
                Route::get('my-signatures', [SignatureController::class, 'listEmployeeSignatures']);
                Route::post('my-signatures', [SignatureController::class, 'storeEmployeeSignature']);
                Route::patch('my-signatures/{signatureUid}/activate', [SignatureController::class, 'setActiveEmployeeSignature']);
                Route::delete('my-signatures/{signatureUid}', [SignatureController::class, 'deleteEmployeeSignature']);

                Route::get('documents', [SignatureController::class, 'generatedDocumentList']);
                Route::get('documents/mine', [SignatureController::class, 'myGeneratedDocumentList']);
                Route::delete('documents/bulk', [SignatureController::class, 'bulkDeleteGeneratedDocument']);
                Route::get('documents/{documentUid}', [SignatureController::class, 'documentSignDetail']);
                Route::delete('documents/{documentUid}', [SignatureController::class, 'deleteGeneratedDocument']);

                // Sign
                Route::get('sign/otp/{employeeDocumentUId}', [SignatureController::class, 'generateSignOtp']);
                Route::post('sign/otp/{employeeDocumentUid}/validate', [SignatureController::class, 'validateOtp']);
                Route::post('sign/{employeeDocumentUid}/apply/{signatureUid}', [SignatureController::class, 'applySignatureToDocument']);
                Route::patch('sign/{employeeDocumentUid}/apply/{signatureUid}', [SignatureController::class, 'updateAppliedSignature']);

                // Render file
                Route::get('/file/employee/{employeeDocumentUid}/render', [SignatureController::class, 'renderEmployeeDocument']);
                Route::get('/file/employee/{employeeDocumentUid}/download', [SignatureController::class, 'downloadCompletedDocument']);
                Route::get('/file/render/{templateUid}/{versionId}', [SignatureController::class, 'renderTemplateDocument']);
            });

        Route::prefix('greatday')
            ->group(function () {
                Route::get('/timezones', 'listTimezones');
                Route::get('/timezones/refresh', 'getGreatdayTimezones')->name('greatday.refreshTimezones');
                Route::get('/religion', 'listReligions');
                Route::get('/religion/refresh', 'getGreatdayReligion')->name('greatday.refreshReligion');
                Route::get('/jobgrade', 'listJobGrades');
                Route::get('/jobgrade/refresh', 'getGreatdayJobGrade')->name('greatday.refreshJobGrade');
                Route::get('/costcenter', 'listCostCenter');
                Route::get('/costcenter/refresh', 'getGreatdayCostCenter')->name('greatday.refreshCostCenter');
                Route::get('/employmentstatus', 'listEmploymentStatuses');
                Route::get('/employmentstatus/refresh', 'getGreatdayEmploymentStatus')->name('greatday.refreshEmploymentStatus');
                Route::get('/worklocation', 'listWorkLocations');
                Route::get('/worklocation/refresh', 'getGreatdayWorkLocation')->name('greatday.refreshWorkLocation');
                Route::get('/shiftpattern', 'listShiftPatterns');
                Route::get('/shiftpattern/refresh', 'getGreatdayShiftPattern')->name('greatday.refreshShiftPattern');
                Route::get('/jobstatus', 'listJobStatuses');
                Route::get('/jobstatus/refresh', 'getGreatdayJobStatus')->name('greatday.refreshJobStatus');
                Route::get('/nationality', 'listNationalities');
                Route::get('/nationality/refresh', 'getGreatdayNationality')->name('greatday.refreshNationality');
                Route::get('/companies', 'listCompanies');
                Route::get('/resigntypes', 'listResignTypes');
                Route::get('/resignreasons', 'listResignReasons');
                Route::get('/companies/refresh', 'getGreatdayCompanies')->name('greatday.refreshCompanies');
                Route::get('/resigntype/refresh', 'getGreatdayResignType')->name('greatday.refreshResignType');
                Route::get('/resignreason/refresh', 'getGreatdayResignReason')->name('greatday.refreshResignReason');
                Route::get('/out-of-sync-employees/list', 'listOutOfSyncEmployees')->name('greatday.listOutOfSyncEmployees');
                Route::get('/out-of-sync-employees', 'getOutOfSyncEmployees')->name('greatday.outOfSyncEmployees');
                Route::post('/employees/sync', 'syncEmployeesFromGreatday')->name('greatday.syncEmployees');
                Route::get('/employees/changes-preview', 'previewEmployeeChanges')->name('greatday.employeeChangesPreview');
                Route::post('/employees/apply-changes', 'applyEmployeeChanges')->name('greatday.applyEmployeeChanges');
                Route::get('/employees/{employeeUid}/link-check', 'checkEmployeeGreatdayLink')->name('greatday.employeeLinkCheck');
                Route::post('/employees/{employeeUid}/link', 'linkEmployeeToGreatday')->name('greatday.employeeLink');
            });
    });

Route::controller(PositionSyncController::class)
    ->middleware(['auth.session'])
    ->prefix('greatday/positions')
    ->group(function () {
        Route::get('/preview', 'preview')->name('greatday.positions.preview');
        Route::post('/sync', 'sync')->name('greatday.positions.sync');
    });

Route::middleware('auth.session')
    ->group(function () {
        Route::get('whatsapp/logs', [WhatsappGroupController::class, 'logs']);
    });

Route::middleware('auth.session')
    ->controller(WhatsappGroupController::class)
    ->prefix('whatsapp-groups')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{whatsapp_group}', 'update');
        Route::delete('/{whatsapp_group}', 'destroy');
        Route::post('/{whatsapp_group}/testing', 'sendTesting');
        Route::get('/community', 'indexCommunity');
        Route::post('/community', 'storeCommunity');
        Route::delete('/community/{communityId}', 'destroyCommunity');
        Route::get('/{groupId}/participants', 'participantsGroup');
        Route::get('/{employeeUid}/user-groups', 'getUserWhatsappGroup');
        Route::get('/{communityId}/community/groups', 'communityGroups');
        Route::post('/{groupId}/sync', 'sync');
        Route::post('/{groupId}/participants', 'addParticipant');
        Route::patch('/{groupId}/participants/set-admin', 'makeUserAsAdmin');
        Route::delete('/{employeeUid}/remove-member/{groupId}', 'removeMemberFromGroup');
    });

Route::middleware('auth.session')
    ->prefix('performanceReport')
    ->group(function () {
        Route::post('export', [PerformanceReportController::class, 'export']);
        Route::get('/{employeeId}', [PerformanceReportController::class, 'performanceDetail']);
        Route::get('getTeams', [PerformanceReportController::class, 'getTeams']);
        Route::get('getMembers/{leaderId}', [PerformanceReportController::class, 'getMembers']);
        Route::get('getMembers/filterMember', [PerformanceReportController::class, 'filterMember']);
    });
