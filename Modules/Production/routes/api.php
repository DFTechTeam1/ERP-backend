<?php

use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\Api\ProjectController;
use Modules\Production\Http\Controllers\ProductionController;

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

Route::middleware(['auth:sanctum'])->prefix('production')->group(function () {
    Route::get('eventTypes', [ProjectController::class, 'getEventTypes']);
    Route::get('classList', [ProjectController::class, 'getClassList']);
    Route::get('status', [ProjectController::class, 'getProjectStatus']);

    Route::get('tasks', [ProjectController::class, 'getAllTasks']);
    Route::get('tasks/{taskUid}', [ProjectController::class, 'detailTask']);

    Route::post('project', [ProjectController::class, 'store']);
    Route::get('project', [ProjectController::class, 'index']);
    Route::get('project/getAll', [ProjectController::class, 'getAllProjects']);
    Route::get('project/getAllBoard', [ProjectController::class, 'getAllBoards']);
    Route::get('project/calendar', [ProjectController::class, 'getProjectCalendars']);
    Route::get('project/marketings', [ProjectController::class, 'getMarketingListForProject']);
    Route::get('project/taskType', [ProjectController::class, 'getTaskTypes']);
    Route::post('project/bulk', [ProjectController::class, 'bulkDelete']);
    Route::get('project/venues', [ProjectController::class, 'autocompleteVenue']);
    Route::get('project/{id}', [ProjectController::class, 'show']);
    Route::post('project/{id}/references', [ProjectController::class, 'storeReferences']);
    Route::post('project/{boardId}/task', [ProjectController::class, 'storeTask']);
    Route::post('project/{taskId}/description', [ProjectController::class, 'storeDescription']);
    Route::post('project/{taskId}/changeTaskBoard', [ProjectController::class, 'changeTaskBoard']);
    Route::post('project/{projectId}/manualChangeTaskBoard', [ProjectController::class, 'manualChangeTaskBoard']);
    Route::post('project/{projectId}/proofOfWork/{taskId}', [ProjectController::class, 'proofOfWork']);
    Route::delete('project/{taskUid}/task', [ProjectController::class, 'deleteTask']);
    Route::put('project/basic/{projectId}', [ProjectController::class, 'updateBasic']);
    Route::put('project/moreDetail/{id}', [ProjectController::class, 'updateMoreDetail']);
    Route::post('project/{projectId}/equipment', [ProjectController::class, 'requestEquipment']);
    Route::get('project/{projectId}/equipment', [ProjectController::class, 'listEquipment']);
    Route::post('project/{projectId}/cancelEquipment', [ProjectController::class, 'cancelRequestEquipment']);
    Route::post('project/{projectId}/updateDeadline', [ProjectController::class, 'updateDeadline']);
    Route::put('project/{projectId}/equipment', [ProjectController::class, 'updateEquipment']);
    Route::post('project/{taskId}/task/assignMember', [ProjectController::class, 'assignMemberToTask']);
    Route::post('project/{id}/references/delete', [ProjectController::class, 'deleteReference']);
    Route::get('project/{projectId}/moveToBoards/{boardId}', [ProjectController::class, 'moveToBoards']);
    Route::get('project/{projectId}/getProjectMembers/{taskId}', [ProjectController::class, 'getProjectMembers']);
    Route::post('project/{projectUid}/updateTaskName/{taskId}', [ProjectController::class, 'updateTaskName']);
    Route::post('project/{projectId}/searchTask/{taskUid}', [ProjectController::class, 'searchTask']);
    Route::get('project/{projectId}/getRelatedTask/{taskUid}', [ProjectController::class, 'getRelatedTask']);
    Route::post('project/{projectId}/uploadTaskAttachment/{taskId}', [ProjectController::class, 'uploadTaskAttachment']);
    Route::get('project/{projectUid}/task/{taskUid}/approve', [ProjectController::class, 'approveTask']);
    Route::get('project/{projectUid}/task/{taskUid}/completed', [ProjectController::class, 'markAsCompleted']);
    Route::post('project/{projectUid}/task/{taskUid}/revise', [ProjectController::class, 'reviseTask']);
    Route::delete('project/{projectUid}/task/{taskUid}/deleteAttachment/{attachmentId}', [ProjectController::class, 'deleteAttachment']);
});

Route::get('production/project/{taskId}/downloadAttachment/{attachmentId}', [ProjectController::class, 'downloadAttachment']);
