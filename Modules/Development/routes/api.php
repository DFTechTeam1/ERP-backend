<?php

use Illuminate\Support\Facades\Route;
use Modules\Development\Http\Controllers\Api\DevelopmentProjectController;

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

Route::middleware(['auth:sanctum'])->prefix('development')->group(function () {
    Route::get('/projects', [DevelopmentProjectController::class, 'index'])->name('development.projects.index');
    Route::post('/projects', [DevelopmentProjectController::class, 'store'])->name('development.projects.store');
    Route::get('/projects/{id}', [DevelopmentProjectController::class, 'show'])->name('development.projects.show');
    Route::put('/projects/{id}', [DevelopmentProjectController::class, 'update'])->name('development.projects.update');
    Route::delete('/projects/{id}', [DevelopmentProjectController::class, 'destroy'])->name('development.projects.destroy');
    Route::get('/projects/{projectUid}/complete', [DevelopmentProjectController::class, 'completeProject'])->name('development.projects.complete');
    Route::get('/projects/{id}/detail', [DevelopmentProjectController::class, 'detail'])->name('development.projects.detail');
    Route::get('/projects/{id}/boards', [DevelopmentProjectController::class, 'updateProjectBoards'])->name('development.projects.boards.update');
    Route::post('/projects/{projectUid}/tasks', [DevelopmentProjectController::class, 'createTask'])->name('development.projects.tasks.store');
    Route::delete('projects/tasks/{taskUid}', [DevelopmentProjectController::class, 'deleteTask'])->name('development.projects.tasks.destroy');
    Route::post('projects/tasks/{taskUid}/proof', [DevelopmentProjectController::class, 'submitTaskProofs'])->name('development.projects.tasks.proof.store');
    Route::post('projects/tasks/{taskUid}/members', [DevelopmentProjectController::class, 'addTaskMember'])->name('development.projects.tasks.members.store');
    Route::get('projects/tasks/{taskUid}/approved', [DevelopmentProjectController::class, 'approveTask'])->name('development.projects.tasks.approved');
    Route::get('projects/tasks/{taskUid}/completeTask', [DevelopmentProjectController::class, 'completeTask'])->name('development.projects.tasks.completed');
    Route::post('projects/tasks/{taskUid}/reviseTask', [DevelopmentProjectController::class, 'reviseTask'])->name('development.projects.tasks.revised');
    Route::post('projects/tasks/{projectUid}/references', [DevelopmentProjectController::class, 'storeReferences'])->name('development.projects.tasks.references.store');
    Route::post('projects/tasks/{taskUid}/attachments', [DevelopmentProjectController::class, 'storeAttachments'])->name('development.projects.tasks.attachments.store');
    Route::get('projects/tasks/{taskUid}/holded', [DevelopmentProjectController::class, 'holdTask'])->name('development.projects.tasks.holded');
    Route::get('projects/tasks/{taskUid}/start', [DevelopmentProjectController::class, 'startTaskAfterHold'])->name('development.projects.tasks.start');
    Route::post('projects/tasks/{taskUid}/deadline', [DevelopmentProjectController::class, 'updateTaskDeadline'])->name('development.projects.tasks.deadline.update');
    Route::get('projects/{projectUid}/getRelatedTask/{taskUid}', [DevelopmentProjectController::class, 'getRelatedTask'])->name('development.projects.tasks.related');
    Route::delete('projects/{projectUid}/references/{referenceId}', [DevelopmentProjectController::class, 'deleteReference'])->name('development.projects.references.destroy');
    Route::delete('projects/{projectUid}/tasks/{taskUid}/attachments/{attachmentId}', [DevelopmentProjectController::class, 'deleteTaskAttachment'])->name('development.projects.tasks.attachments.destroy');
    Route::get('projects/tasks/{taskUid}/move/{boardId}', [DevelopmentProjectController::class, 'moveBoardId'])->name('development.projects.tasks.move');
});
