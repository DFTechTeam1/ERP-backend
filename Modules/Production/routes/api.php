<?php

use App\Http\Middleware\PermissionCheck;
use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\Api\DeadlineChangeReasonController;
use Modules\Production\Http\Controllers\Api\InteractiveController;
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

        Route::get('deadlineReason/getAll', [DeadlineChangeReasonController::class, 'getAll']);
        Route::resource('deadlineReason', DeadlineChangeReasonController::class);

        Route::get('tasks', [ProjectController::class, 'getAllTasks']);
        Route::get('tasks/status', [ProjectController::class, 'getTaskStatus']);
        Route::get('tasks/{taskUid}', [ProjectController::class, 'detailTask']);

        Route::get('refunds', [ProjectController::class, 'listRefunds'])->name('refund.list');
        Route::get('refunds/{refundUid}', [ProjectController::class, 'detailRefund'])->name('refund.detail');
        Route::delete('refunds/{refundUid}', [ProjectController::class, 'deleteRefund'])->name('refund.delete');
        Route::post('refunds/{refundUid}/payment', [ProjectController::class, 'makeRefundPayment'])->name('refund.payment');
        Route::post('project', [ProjectController::class, 'store']);
        Route::get('project', [ProjectController::class, 'index'])->name('project-list');
        Route::get('project/getAll', [ProjectController::class, 'getAllProjects']);
        Route::post('project/deals', [ProjectController::class, 'storeProjectDeals'])->name('project-deal.store');
        Route::get('project/deals', [ProjectController::class, 'listProjectDeals'])->name('project-deal.list');
        Route::get('project/interactive-requests', [ProjectController::class, 'listInteractiveRequests'])->name('interactive-request.list');
        Route::get('project/deals/price-changes', [ProjectController::class, 'requestChangesList'])->name('project-deal.requestChangesList');
        Route::get('project/deals/selection', [ProjectController::class, 'requestProjectDealSelectionList'])->name('project-deal.requestSelectionList');
        Route::get('project/initProjectCount', [ProjectController::class, 'initProjectCount']);
        Route::get('project/deals/{projectDealUid}', [ProjectController::class, 'detailProjectDeal']);
        Route::put('project/deals/{projectDealUid}', [ProjectController::class, 'updateProjectDeal']);
        Route::post('project/deals/{projectDealUid}/cancel', [ProjectController::class, 'cancelProjectDeal'])->name('project-deal.cancel');
        Route::post('project/deals/{projectDealUid}/refund', [ProjectController::class, 'storeRefund'])->name('project-deal.refund');
        Route::post('project/deals/{projectDealUid}/quotation', [ProjectController::class, 'addMoreQuotation']);
        Route::post('project/deals/{projectDealUid}/update', [ProjectController::class, 'updateFinalDeal'])->name('project-deal.updateFinal');
        Route::post('project/deals/{projectDealUid}/interactives', [InteractiveController::class, 'store'])->name('project-deal.addInteractive');
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
        Route::post('project/{projectUid}/calculate-prorate', [ProjectController::class, 'calculateProratePoint']);
        Route::get('project/{projectUid}/getTaskTeamForReview', [ProjectController::class, 'getTaskTeamForReview']);
        Route::get('project/{projectUid}/precheck', [ProjectController::class, 'precheck']);
        Route::post('project/{projectUid}/completeUnfinishedTask', [ProjectController::class, 'completeUnfinishedTask']);

        // interactives
        Route::get('interactives', [InteractiveController::class, 'index'])->name('interactives.list');
        Route::post('interactives/storeTask/{projectUid}', [InteractiveController::class, 'storeTask'])
            ->middleware(PermissionCheck::class.':create_interactive_task')
            ->name('interactives.storeTask');

        Route::get('interactives/{interactiveUid}/getPicForSubtitute', [InteractiveController::class, 'getPicForSubtitute']);
        Route::post('interactives/status/{interactiveUid}', [InteractiveController::class, 'changeStatus'])
            ->middleware(PermissionCheck::class.':change_interactive_status')
            ->name('interactives.changeStatus');
        Route::get('interactives/cancel/{interactiveUid}', [InteractiveController::class, 'cancelProject'])->name('interactives.cancel');
        Route::get('interactives/picScheduler/{interactiveUid}', [InteractiveController::class, 'getPicScheduler'])
            ->middleware(PermissionCheck::class.':assign_interactive_pic')
            ->name('interactives.getPicScheduler');
        Route::post('interactives/assignPic/{interactiveUid}', [InteractiveController::class, 'assignPicToProject'])
            ->middleware(PermissionCheck::class.':assign_interactive_pic')
            ->name('interactives.assignPic');
        Route::post('interactives/substitute/{interactiveUid}', [InteractiveController::class, 'substitutePicInProject'])
            ->middleware(PermissionCheck::class.':assign_interactive_pic')
            ->name('interactives.substitutePic');
        Route::post('interactives/tasks/{taskUid}/members', [InteractiveController::class, 'addTaskMember'])
            ->middleware(PermissionCheck::class.':assign_interactive_task_member')
            ->name('interactives.tasks.members.store');
        Route::get('interactives/tasks/{taskUid}/approved', [InteractiveController::class, 'approveTask'])
            ->middleware(PermissionCheck::class.':approve_interactive_task')
            ->name('interactives.tasks.approved');
        Route::post('interactives/tasks/{taskUid}/proof', [InteractiveController::class, 'submitTaskProofs'])
            ->middleware(PermissionCheck::class.':submit_interactive_task')
            ->name('interactives.tasks.proof.store');
        Route::get('interactives/tasks/{taskUid}/completeTask', [InteractiveController::class, 'completeTask'])
            ->middleware(PermissionCheck::class.':complete_interactive_task')
            ->name('interactives.tasks.completed');
        Route::delete('interactives/tasks/{taskUid}', [InteractiveController::class, 'deleteTask'])
            ->middleware(PermissionCheck::class.':delete_interactive_task')
            ->name('interactives.tasks.destroy');
        Route::post('interactives/tasks/{taskUid}/reviseTask', [InteractiveController::class, 'reviseTask'])
            ->middleware(PermissionCheck::class.':revise_interactive_task')
            ->name('interactives.tasks.revised');
        Route::post('interactives/tasks/{taskUid}/description', [InteractiveController::class, 'storeDescription'])
            ->middleware(PermissionCheck::class.':update_description_interactive_task')
            ->name('interactives.tasks.description.store');
        Route::post('interactives/tasks/{taskUid}/holded', [InteractiveController::class, 'holdTask'])
            ->middleware(PermissionCheck::class.':hold_interactive_task')
            ->name('interactives.tasks.holded');
        Route::get('interactives/tasks/{taskUid}/start', [InteractiveController::class, 'startTaskAfterHold'])
            ->middleware(PermissionCheck::class.':hold_interactive_task')
            ->name('interactives.tasks.start');
        Route::post('interactives/tasks/{projectUid}/references', [InteractiveController::class, 'storeReferences'])
            ->middleware(PermissionCheck::class.':create_interactive_task_attachment')
            ->name('interactives.tasks.references.store');
        Route::delete('interactives/{projectUid}/references/{referenceId}', [InteractiveController::class, 'deleteReference'])
            ->middleware(PermissionCheck::class.':delete_interactive_reference')
            ->name('interactives.references.destroy');
        Route::post('interactives/tasks/{taskUid}/deadline', [InteractiveController::class, 'updateTaskDeadline'])
            ->middleware(PermissionCheck::class.':update_deadline_interactive_task')
            ->name('interactives.tasks.deadline.update');
        Route::post('interactives/{interactiveUid}/tasks/filter', [InteractiveController::class, 'filterTasks'])->name('interactives.tasks.filter');
        Route::delete('interactives/{interactiveUid}/tasks/{taskUid}/attachments/{imageId}', [InteractiveController::class, 'deleteTaskAttachment'])
            ->middleware(PermissionCheck::class.':delete_interactive_task_attachment')
            ->name('interactives.tasks.attachments.destroy');
        Route::get('interactives/approve/{requestId}', [InteractiveController::class, 'approveInteractive'])->name('interactives.approve');
        Route::get('interactives/reject/{requestId}', [InteractiveController::class, 'rejectInteractiveRequest'])->name('interactives.reject');
        Route::get('interactives/{uid}', [InteractiveController::class, 'show'])->name('interactives.show');


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
        Route::post('project/{taskId}/afpat-status', [ProjectController::class, 'updateAfterPartyStatus']);
        Route::post('project/{projectId}/manualChangeTaskBoard', [ProjectController::class, 'manualChangeTaskBoard']);
        Route::post('project/{projectId}/proofOfWork/{taskId}', [ProjectController::class, 'proofOfWork'])->name('task.proof.store');
        Route::delete('project/{taskUid}/task', [ProjectController::class, 'deleteTask']);
        Route::put('project/basic/{projectId}', [ProjectController::class, 'updateBasic']);
        Route::put('project/moreDetail/{id}', [ProjectController::class, 'updateMoreDetail']);
        Route::post('project/{projectId}/equipment', [ProjectController::class, 'requestEquipment']);
        Route::get('project/{projectId}/equipment', [ProjectController::class, 'listEquipment']);
        Route::post('project/{projectId}/cancelEquipment', [ProjectController::class, 'cancelRequestEquipment']);
        Route::post('project/{projectUid}/cancelProject', [ProjectController::class, 'cancelProject']);
        Route::put('project/{projectId}/equipment', [ProjectController::class, 'updateEquipment']);
        Route::post('project/{taskId}/task/assignMember', [ProjectController::class, 'assignMemberToTask'])->name('task.assign-member');
        Route::post('project/{id}/references/delete', [ProjectController::class, 'deleteReference']);
        Route::get('project/{projectId}/moveToBoards/{boardId}', [ProjectController::class, 'moveToBoards']);
        Route::post('project/{projectUid}/updateDeadline/{taskUid}', [ProjectController::class, 'updateDeadline'])->name('task.update-deadline');
        Route::get('project/{projectUid}/getPicTeams/{picUid}', [ProjectController::class, 'getPicTeams']);
        Route::get('project/{projectId}/getProjectMembers/{taskId}', [ProjectController::class, 'getProjectMembers']);
        Route::post('project/{projectUid}/updateTaskName/{taskId}', [ProjectController::class, 'updateTaskName']);
        Route::post('project/{projectId}/searchTask/{taskUid}', [ProjectController::class, 'searchTask']);
        Route::get('project/{projectId}/getRelatedTask/{taskUid}', [ProjectController::class, 'getRelatedTask']);
        Route::post('project/{projectId}/uploadTaskAttachment/{taskId}', [ProjectController::class, 'uploadTaskAttachment']);
        Route::get('project/{projectUid}/task/{taskUid}/approve', [ProjectController::class, 'approveTask'])->name('task.approve');
        Route::get('project/{projectUid}/task/{taskUid}/completed', [ProjectController::class, 'markAsCompleted'])->name('tasks.completed');
        Route::post('project/{projectUid}/task/{taskUid}/revise', [ProjectController::class, 'reviseTask'])->name('task.revise');
        Route::post('project/{projectUid}/task/{taskUid}/distribute', [ProjectController::class, 'distributeModellerTask']);
        Route::post('project/{projectUid}/task/{taskUid}/hold', [ProjectController::class, 'holdTask'])->name('task.hold');
        Route::get('project/{projectUid}/task/{taskUid}/startTask', [ProjectController::class, 'startTask'])->name('task.state');
        Route::get('project/{projectUid}/task/{employeeId}/listTask', [ProjectController::class, 'getEmployeeTaskList']);
        Route::delete('project/{projectUid}/task/{taskUid}/deletAettachment/{attachmentId}', [ProjectController::class, 'deleteAttachment']);

        // incharges
        Route::get('incharges', [ProjectController::class, 'inchargeList']);

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
Route::get('production/project/deal/c/approve/{projectDetailChangesUid}', [ProjectController::class, 'approveChangesProjectDeal'])->name('production.project-deal.approveChanges');
Route::get('production/project/deal/c/reject/{projectDetailChangesUid}', [ProjectController::class, 'rejectChangesProjectDeal'])->name('production.project-deal.rejectChanges');
