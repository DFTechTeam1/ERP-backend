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

    Route::post('project', [ProjectController::class, 'store']);
    Route::get('project', [ProjectController::class, 'index']);
    Route::get('project/{id}', [ProjectController::class, 'show']);
    Route::post('project/{id}/references', [ProjectController::class, 'storeReferences']);
    Route::post('project/{boardId}/task', [ProjectController::class, 'storeTask']);
    Route::post('project/{taskId}/description', [ProjectController::class, 'storeDescription']);
    Route::get('project/{projectId}/getProjectMembers', [ProjectController::class, 'getProjectMembers']);
    Route::put('project/basic/{id}', [ProjectController::class, 'updateBasic']);
    Route::put('project/moreDetail/{id}', [ProjectController::class, 'updateMoreDetail']);
    Route::post('project/{taskId}/task/assignMember', [ProjectController::class, 'assignMemberToTask']);
    Route::post('project/{id}/references/delete', [ProjectController::class, 'deleteReference']);
});
