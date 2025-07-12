<?php

use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\Api\DeadlineChangeReasonController;
use Modules\Production\Http\Controllers\Api\ProjectController;
use Modules\Production\Http\Controllers\Api\QuotationController;
use Modules\Production\Http\Controllers\Api\TeamTransferController;

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

Route::get('production/project/{projectUid}/downloadReferences', [ProjectController::class, 'downloadReferences']);

Route::middleware(['auth:sanctum'])
    ->name('production.')
    ->prefix('production')->group(function () {
        Route::get('eventTypes', [ProjectController::class, 'getEventTypes']);
        Route::get('classList', [ProjectController::class, 'getClassList']);
        Route::get('status', [ProjectController::class, 'getProjectStatus']);

        Route::resource('deadlineReason', DeadlineChangeReasonController::class);

        Route::get('tasks', [ProjectController::class, 'getAllTasks']);
        Route::get('tasks/status', [ProjectController::class, 'getTaskStatus']);
        Route::get('tasks/{taskUid}', [ProjectController::class, 'detailTask']);

        Route::post('project', [ProjectController::class, 'store']);
        Route::get('project', [ProjectController::class, 'index'])->name('project-list');
        Route::get('project/getAll', [ProjectController::class, 'getAllProjects']);
        Route::post('project/deals', [ProjectController::class, 'storeProjectDeals'])->name('project-deal.store');
        Route::get('project/deals', [ProjectController::class, 'listProjectDeals']);
        Route::get('project/initProjectCount', [ProjectController::class, 'initProjectCount']);
        Route::get('project/deals/{projectDealUid}', [ProjectController::class, 'detailProjectDeal']);
        Route::put('project/deals/{projectDealUid}', [ProjectController::class, 'updateProjectDeal']);
        Route::post('project/deals/{projectDealUid}/quotation', [ProjectController::class, 'addMoreQuotation']);
        Route::delete('project/deals/{projectDealUid}', [ProjectController::class, 'deleteProjectDeal']);
        Route::get('project/deals/publish/{projectDealUid}/{type}', [ProjectController::class, 'publishProjectDeal']);
        Route::get('project/getAllBoard', [ProjectController::class, 'getAllBoards']);
        Route::get('project/calendar', [ProjectController::class, 'getProjectCalendars']);
        Route::get('project/getQuotationNumber', [ProjectController::class, 'getQuotationNumber']);
        Route::get('project/initEntertainmentTeam', [ProjectController::class, 'initEntertainmentTeam']);
        Route::get('project/calculation/formula', [ProjectController::class, 'getCalculationFormula']);
        Route::get('project/marketings', [ProjectController::class, 'getMarketingListForProject']);
        Route::get('project/taskType', [ProjectController::class, 'getTaskTypes']);
        Route::get('project/getProjectsFolder', [ProjectController::class, 'getProjectsFolder']);
        Route::post('project/bulk', [ProjectController::class, 'bulkDelete']);
        Route::get('project/venues', [ProjectController::class, 'autocompleteVenue']);
        Route::get('project/getProjectYears', [ProjectController::class, 'getProjectYears']);
        Route::get('project/getProjectFolderDetail', [ProjectController::class, 'getProjectFolderDetail']);
        Route::get('project/{id}', [ProjectController::class, 'show']);
        Route::post('project/checkHighSeason', [ProjectController::class, 'checkHighSeason']);
        Route::get('project/{projectUid}/getTaskTeamForReview', [ProjectController::class, 'getTaskTeamForReview']);
        Route::get('project/{projectUid}/precheck', [ProjectController::class, 'precheck']);
        Route::post('project/{projectUid}/completeUnfinishedTask', [ProjectController::class, 'completeUnfinishedTask']);

        // songs
        Route::post('project/{projectUid}/song', [ProjectController::class, 'storeSongs'])->name('projects.storeSongs');
        Route::post('project/{projectUid}/bulkAssignWorkerForSong', [ProjectController::class, 'bulkAssignWorkerForSong'])->name('projects.bulkAssignWorkerForSong');
        Route::post('project/{projectUid}/tasks/filter', [ProjectController::class, 'filterTasks']);
        Route::get('project/{projectUid}/song/{songUid}', [ProjectController::class, 'detailSong'])->name('projects.detailSongs');
        Route::put('project/{projectUid}/song/{songUid}', [ProjectController::class, 'updateSong'])->name('projects.updateSongs');
        Route::delete('project/{projectUid}/song/{songUid}', [ProjectController::class, 'deleteSong'])->name('projects.deleteSongs');
        Route::put('project/{projectUid}/song/{songUid}/confirmEditSong', [ProjectController::class, 'confirmEditSong'])->name('projects.confirmEditSong');
        Route::get('project/{projectUid}/song/{songUid}/approve', [ProjectController::class, 'startWorkOnSong'])->name('projects.startWorkOnSong');
        Route::get('project/{projectUid}/song/{songUid}/approveUpper', [ProjectController::class, 'songApproveWork'])->name('projects.songApproveWork');
        Route::post('project/{projectUid}/song/{songUid}/revise', [ProjectController::class, 'songRevise'])->name('projects.songRevise');
        Route::put('project/{projectUid}/song/{songUid}/confirmDeleteSong', [ProjectController::class, 'confirmDeleteSong'])->name('projects.confirmDeleteSong');
        Route::post('project/{projectUid}/song/report/{songUid}', [ProjectController::class, 'songReportAsDone'])->name('projects.songReportAsDone');
        Route::post('project/{projectUid}/song/distribute/{songUid}', [ProjectController::class, 'distributeSong'])->name('projects.distributeSong');
        Route::post('project/{projectUid}/song/reject/{songUid}', [ProjectController::class, 'rejectEditSong'])->name('projects.rejectEditSong');
        Route::post('project/{projectUid}/song/subtituteSongPic/{songUid}', [ProjectController::class, 'subtituteSongPic'])->name('projects.subtituteSongPic');
        Route::get('project/{projectUid}/song/removePic/{songUid}', [ProjectController::class, 'removePicSong'])->name('projects.removePicSong');

        // entertainment
        Route::get('/project/{projectUid}/entertainment/listMember', [ProjectController::class, 'entertainmentListMember'])->name('projecjts.entertainmentListMember');
        Route::get('/project/{projectUid}/entertainment/workload', [ProjectController::class, 'entertainmentMemberWorkload'])->name('projecjts.entertainmentMemberWorkload');

        Route::post('project/customer/add', [ProjectController::class, 'storeCustomer']);
        Route::get('project/customer/list', [ProjectController::class, 'getCustomer']);
        Route::get('project/{projectUid}/statusses', [ProjectController::class, 'getProjectStatusses']);
        Route::get('project/{projectUid}/prepareFinalCheck', [ProjectController::class, 'prepareFinalCheck']);
        Route::get('project/scheduler/{projectUid}', [ProjectController::class, 'getAllSchedulerProjects']);
        Route::get('project/{projectUid}/getPicScheduler', [ProjectController::class, 'getPicScheduler']);
        Route::post('project/{projectUid}/assignPic', [ProjectController::class, 'assignPic']);
        Route::post('project/{projectUid}/subtitutePic', [ProjectController::class, 'subtitutePic']);
        Route::get('project/{projectUid}/getPicForSubtitute', [ProjectController::class, 'getPicForSubtitute']);
        Route::get('project/{projectUid}/readyToGo', [ProjectController::class, 'readyToGo']);
        Route::delete('project/{projectUid}/removeAllVJ', [ProjectController::class, 'removeAllVJ']);
        Route::post('project/{id}/references', [ProjectController::class, 'storeReferences'])->name('store-reference');
        Route::post('project/{projectUid}/completeProject', [ProjectController::class, 'completeProject']);
        Route::post('project/{projectUid}/assignVj', [ProjectController::class, 'assignVJ']);
        Route::get('project/getTargetPicsAndTaskList/{projectUid}', [ProjectController::class, 'getTargetPicsAndTaskList']); // get pic list for request team member (exclude logged accont)
        Route::post('project/{projectUid}/loadTeamMember', [ProjectController::class, 'loadTeamMember']);
        Route::post('project/{projectUid}/uploadShowreels', [ProjectController::class, 'uploadShowreels']);
        Route::post('project/{projectUid}/requestEntertainment', [ProjectController::class, 'requestEntertainment']);
        Route::post('project/{projectUid}/changeStatus', [ProjectController::class, 'changeStatus']);
        Route::get('project/{id}/getBoards', [ProjectController::class, 'getProjectBoards']);
        Route::get('project/{id}/getProjectTeams', [ProjectController::class, 'getProjectTeams']);
        Route::post('project/{boardId}/task', [ProjectController::class, 'storeTask'])->name('storeTask');
        Route::post('project/{taskId}/description', [ProjectController::class, 'storeDescription']);
        Route::post('project/{taskId}/changeTaskBoard', [ProjectController::class, 'changeTaskBoard']);
        Route::post('project/{taskId}/manualMoveBoard', [ProjectController::class, 'manualMoveBoard']);
        Route::post('project/{taskId}/returnEquipment', [ProjectController::class, 'returnEquipment']);
        Route::post('project/{projectId}/manualChangeTaskBoard', [ProjectController::class, 'manualChangeTaskBoard']);
        Route::post('project/{projectId}/proofOfWork/{taskId}', [ProjectController::class, 'proofOfWork']);
        Route::delete('project/{taskUid}/task', [ProjectController::class, 'deleteTask']);
        Route::put('project/basic/{projectId}', [ProjectController::class, 'updateBasic']);
        Route::put('project/moreDetail/{id}', [ProjectController::class, 'updateMoreDetail']);
        Route::post('project/{projectId}/equipment', [ProjectController::class, 'requestEquipment']);
        Route::get('project/{projectId}/equipment', [ProjectController::class, 'listEquipment']);
        Route::post('project/{projectId}/cancelEquipment', [ProjectController::class, 'cancelRequestEquipment']);
        Route::post('project/{projectUid}/cancelProject', [ProjectController::class, 'cancelProject']);
        Route::post('project/{projectId}/updateDeadline', [ProjectController::class, 'updateDeadline']);
        Route::put('project/{projectId}/equipment', [ProjectController::class, 'updateEquipment']);
        Route::post('project/{taskId}/task/assignMember', [ProjectController::class, 'assignMemberToTask']);
        Route::post('project/{id}/references/delete', [ProjectController::class, 'deleteReference']);
        Route::get('project/{projectId}/moveToBoards/{boardId}', [ProjectController::class, 'moveToBoards']);
        Route::get('project/{projectUid}/getPicTeams/{picUid}', [ProjectController::class, 'getPicTeams']);
        Route::get('project/{projectId}/getProjectMembers/{taskId}', [ProjectController::class, 'getProjectMembers']);
        Route::post('project/{projectUid}/updateTaskName/{taskId}', [ProjectController::class, 'updateTaskName']);
        Route::post('project/{projectId}/searchTask/{taskUid}', [ProjectController::class, 'searchTask']);
        Route::get('project/{projectId}/getRelatedTask/{taskUid}', [ProjectController::class, 'getRelatedTask']);
        Route::post('project/{projectId}/uploadTaskAttachment/{taskId}', [ProjectController::class, 'uploadTaskAttachment']);
        Route::get('project/{projectUid}/task/{taskUid}/approve', [ProjectController::class, 'approveTask']);
        Route::get('project/{projectUid}/task/{taskUid}/completed', [ProjectController::class, 'markAsCompleted']);
        Route::post('project/{projectUid}/task/{taskUid}/revise', [ProjectController::class, 'reviseTask']);
        Route::post('project/{projectUid}/task/{taskUid}/distribute', [ProjectController::class, 'distributeModellerTask']);
        Route::post('project/{projectUid}/task/{taskUid}/hold', [ProjectController::class, 'holdTask']);
        Route::get('project/{projectUid}/task/{taskUid}/startTask', [ProjectController::class, 'startTask']);
        Route::get('project/{projectUid}/task/{employeeId}/listTask', [ProjectController::class, 'getEmployeeTaskList']);
        Route::delete('project/{projectUid}/task/{taskUid}/deletAettachment/{attachmentId}', [ProjectController::class, 'deleteAttachment']);

        // Quotations
        Route::get('quotations', [QuotationController::class, 'index']);
        Route::get('quotations/pagination', [QuotationController::class, 'pagination']);
        Route::put('quotations/{id}', [QuotationController::class, 'update']);
        Route::delete('quotations/{id}', [QuotationController::class, 'destroy']);
        Route::post('quotations', [QuotationController::class, 'store']);
        
        Route::get('team-transfers', [TeamTransferController::class, 'index']);
        Route::post('team-transfers/cancel', [TeamTransferController::class, 'cancelRequest']);
        Route::post('team-transfers/chooseTeam/{transferUid}', [TeamTransferController::class, 'chooseTeam']);
        Route::get('team-transfers/complete/{transferUid}', [TeamTransferController::class, 'completeRequest']);
        Route::delete('team-transfers/delete/{transferUid}', [TeamTransferController::class, 'destroy']);
        Route::post('team-transfers/reject/{transferUid}', [TeamTransferController::class, 'rejectRequest']);
        Route::post('team-transfers/approve-selection/{transferUid}', [TeamTransferController::class, 'approveSelection']);
        Route::get('team-transfers/approve/{transferUid}/{deviceAction}', [TeamTransferController::class, 'approveRequest']);
        Route::get('team-transfers/{transferUid}/getMembersToLend/{employeeUid}', [TeamTransferController::class, 'getMembersToLend']);
    });

Route::get('production/project/{taskId}/downloadAttachment/{attachmentId}', [ProjectController::class, 'downloadAttachment']);
Route::get('production/project/{projectUid}/downloadProofOfWork/{proofOfWorkId}', [ProjectController::class, 'downloadProofOfWork']);
Route::get('production/project/{projectUid}/downloadReviseMedia/{reviseId}', [ProjectController::class, 'downloadReviseMedia']);
