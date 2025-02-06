<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserRoleManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Http\Requests\Project\BasicUpdate;
use Modules\Production\Http\Requests\Project\BulkAssignSong;
use Modules\Production\Http\Requests\Project\HoldTask;
use Modules\Production\Http\Requests\Project\LoanTeamMember;
use Modules\Production\Http\Requests\Project\ChangeStatus;
use Modules\Production\Http\Requests\Project\ChangeTaskBoard;
use Modules\Production\Http\Requests\Project\Create;
use Modules\Production\Http\Requests\Project\CreateDescription;
use Modules\Production\Http\Requests\Project\CreateTask;
use Modules\Production\Http\Requests\Project\MoreDetailUpdate;
use Modules\Production\Http\Requests\Project\StoreReferences;
use Modules\Production\Http\Requests\Project\UpdateDeadline;
use Modules\Production\Http\Requests\Project\UploadProofOfWork;
use Modules\Production\Http\Requests\Project\ReviseTask;
use Modules\Production\Services\ProjectService;
use \Modules\Production\Http\Requests\Project\ManualChangeTaskBoard;
use Modules\Production\Http\Requests\Project\UploadShowreels;
use Modules\Production\Http\Requests\Project\CompleteProject;
use Modules\Production\Http\Requests\Project\DistributeSong;
use Modules\Production\Http\Requests\Project\RejectEditSong;
use Modules\Production\Http\Requests\Project\RequestSong;
use Modules\Production\Http\Requests\Project\SubtituteWorkerSong;
use Modules\Production\Http\Requests\Project\UpdateSong;
use Modules\Production\Services\TestingService;

class ProjectController extends Controller
{
    private $service;

    private $testingService;

    public function __construct(
        ProjectService $projectService,
        TestingService $testingService
    )
    {
        $this->service = $projectService;

        $this->testingService = $testingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->testingService->list(
            'id,uid,name,project_date,venue,event_type,collaboration,note,marketing_id,status,classification,led_area,led_detail,project_class_id',
            '',
            [
                'marketing:id,name,employee_id',
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id',
                'marketings.marketing:id,name,employee_id',
                'projectClass:id,name,color',
                'vjs.employee:id,nickname',
                'equipments:id,project_id,inventory_id,status,is_returned'
            ]
        ));
    }

    /**
     * Get all project based on user role
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllProjects()
    {
        return apiResponse($this->service->getAllProjects());
    }

    /**
     * Get Event Types
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEventTypes()
    {
        return apiResponse($this->service->getEventTypes());
    }

    /**
     * Get available project status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectStatus()
    {
        return apiResponse($this->service->getProjectStatus());
    }

    /**
     * Get Classification List
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassList()
    {
        return apiResponse($this->service->getClassList());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        return apiResponse($this->service->store($request->validated()));
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return apiResponse($this->service->show($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //

        return response()->json([]);
    }

    /**
     * Update basic project information
     *
     * @param BasicUpdate $request
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBasic(BasicUpdate $request, string $projectId)
    {
        return apiResponse($this->service->updateBasic($request->validated(), $projectId));
    }

    /**
     * Update more detail
     *
     * @param MoreDetailUpdate $request
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMoreDetail(MoreDetailUpdate $request, string $uid)
    {
        return apiResponse($this->service->updateMoreDetail($request->validated(), $uid));
    }

    /**
     * Store project references
     *
     * @param StoreReferences $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeReferences(StoreReferences $request, string $id)
    {
        return apiResponse($this->service->storeReferences($request->validated(), $id));
    }

    /**
     * Create new task on selected board
     *
     * @param CreateTask $request
     * @param int $boardId
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeTask(CreateTask $request, $boardId)
    {
        return apiResponse($this->service->storeTask($request->validated(), (int) $boardId));
    }

    /**
     * Delete selected task
     *
     * @param string $taskUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteTask(string $taskUid)
    {
        return apiResponse($this->service->deleteTask($taskUid));
    }

    /**
     * Get all task types
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTaskTypes()
    {
        return apiResponse($this->service->getTaskTypes());
    }

    /**
     * Add task description
     *
     * @param CreateDescription $request
     * @param string $taskId
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeDescription(CreateDescription $request, $taskId)
    {
        return apiResponse($this->service->storeDescription($request->validated(), (string) $taskId));
    }

    /**
     * Get teams of selected project
     *
     * @param string $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectMembers($projectId, string $taskId)
    {
        return apiResponse($this->service->getProjectMembers((int) $projectId, $taskId));
    }

    /**
     * Assign members / employees to selected task
     *
     * @param Request $request
     * @param string $taskId
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignMemberToTask(Request $request, $taskId)
    {
        return apiResponse(
            $this->service->assignMemberToTask($request->all(), (string) $taskId)
        );
    }

    /**
     * Delete selected image reference
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteReference(Request $request, string $id)
    {
        return apiResponse($this->service->deleteReference($request->image_ids, $id));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Delete multiple data
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        return apiResponse($this->service->bulkDelete(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }

    /**
     * Delete multiple data
     * @param string $projectUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeAllVJ(string $projectUid)
    {
        return apiResponse($this->service->removeAllVJ($projectUid));
    }

    /**
     * Store new request equipment
     *
     * @param \Modules\Production\Http\Requests\Project\RequestEquipment $request
     * @param string $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestEquipment(\Modules\Production\Http\Requests\Project\RequestEquipment $request, string $projectId)
    {
        return apiResponse($this->service->requestEquipment($request->validated(), $projectId));
    }

    /**
     * List of project equipments
     *
     * @param string $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function listEquipment(string $projectId)
    {
        return apiResponse($this->service->listEquipment($projectId));
    }

    /**
     * Update selected equipment
     *
     * @param \Modules\Production\Http\Requests\Project\UpdateEquipment $request
     * @param string $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEquipment(\Modules\Production\Http\Requests\Project\UpdateEquipment $request, string $projectId)
    {
        return apiResponse($this->service->updateEquipment($request->validated(), $projectId));
    }

    /**
     * Cancel request equipment
     *
     * @param Request $request
     * @param string $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelRequestEquipment(Request $request, string $projectId)
    {
        return apiResponse($this->service->cancelRequestEquipment($request->toArray(), $projectId));
    }

    /**
     * Add project deadline
     *
     * @param UpdateDeadline $request
     * @param string $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDeadline(UpdateDeadline $request, string $projectId)
    {
        return apiResponse($this->service->updateDeadline($request->validated(), $projectId));
    }

    public function uploadTaskAttachment(\Modules\Production\Http\Requests\Project\TaskAttachment $request, string $projectId, string $taskId)
    {
        return apiResponse($this->service->uploadTaskAttachment($request->validated(), $taskId, $projectId));
    }

    public function searchTask(Request $request, string $projectUid, string $taskUid)
    {
        return apiResponse($this->service->searchTask($projectUid, $taskUid, $request->search ?? ''));
    }

    public function getRelatedTask(string $projectUid, string $taskUid)
    {
        return apiResponse($this->service->getRelatedTask($projectUid, $taskUid));
    }

    public function downloadAttachment(string $taskId, int $attachmentId)
    {
        return $this->service->downloadAttachment($taskId, $attachmentId);
    }

    public function deleteAttachment(string $projectUid, string $taskUid, int $attachmentId)
    {
        return apiResponse($this->service->deleteAttachment($projectUid, $taskUid, $attachmentId));
    }

    /**
     * Change board of task (When user move a task)
     *
     * @param ChangeTaskBoard $request
     * @param string $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeTaskBoard(ChangeTaskBoard $request, string $projectId)
    {
        return apiResponse($this->service->changeTaskBoard($request->validated(), $projectId));
    }

    /**
     * Change board of task (When user move a task)
     *
     * @param ChangeTaskBoard $request
     * @param string $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function manualMoveBoard(ChangeTaskBoard $request, string $projectId)
    {
        return apiResponse($this->service->manualMoveBoard($request->validated(), $projectId));
    }

    /**
     * * Change board of task (When user move a task)
     *
     * @param \Modules\Production\Http\Requests\Project\ManualChangeTaskBoard $request
     * @param string $projectId
     */
    public function manualChangeTaskBoard(ManualChangeTaskBoard $request, string $projectId)
    {
        return apiResponse($this->service->manualChangeTaskBoard($request->validated(), $projectId));
    }

    public function proofOfWork(UploadProofOfWork $request, string $projectId, string $taskId)
    {
        return apiResponse($this->service->proofOfWork($request->validated(), $projectId, $taskId));
    }

    public function updateTaskName(Request $request, string $projectUid, string $taskId)
    {
        return apiResponse($this->service->updateTaskName($request->all(), $projectUid, $taskId));
    }

    public function moveToBoards(string $projectId, int $boardId)
    {
        return apiResponse($this->service->getMoveToBoards($boardId, $projectId));
    }

    public function autocompleteVenue()
    {
        return apiResponse($this->service->autocompleteVenue());
    }

    public function getAllTasks()
    {
        return apiResponse($this->service->getAllTasks());
    }

    public function detailTask(string $taskUid)
    {
        return apiResponse($this->service->detailTask($taskUid));
    }

    /**
     * Function to get marketing list
     * This is used in project form
     * Result should have marketing position + directors
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMarketingListForProject()
    {
        return apiResponse($this->service->getMarketingListForProject());
    }

    public function approveTask(string $projectUid, string $taskUid)
    {
        return apiResponse($this->service->approveTask($projectUid, $taskUid));
    }

    public function markAsCompleted(string $projectUid, string $taskUid)
    {
        return apiResponse($this->service->markAsCompleted($projectUid, $taskUid));
    }

    /**
     * Revise task
     *
     * $request data will be:
     * 1. string reason -> required
     * 2. blob file -> nullable
     *
     * @param ReviseTask $request
     * @param string $projectUid
     * @param string $taskUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function reviseTask(ReviseTask $request, string $projectUid, string $taskUid)
    {
        return apiResponse($this->service->reviseTask($request->validated(), $projectUid, $taskUid));
    }

    public function holdTask(string $projectUid, string $taskUid, HoldTask $request)
    {
        return apiResponse($this->service->holdTask($projectUid, $taskUid, $request->validated()));
    }

    public function startTask(string $projectUid, string $taskUid)
    {
        return apiResponse($this->service->startTask($projectUid, $taskUid));
    }

    public function getProjectCalendars()
    {
        return apiResponse($this->service->getProjectCalendars());
    }

    public function getProjectBoards(string $projectId)
    {
        return apiResponse($this->service->getProjectBoards($projectId));
    }

    public function getProjectTeams(string $projectUid)
    {
        return apiResponse($this->service->getProjectTeamsForTask($projectUid));
    }

    public function getProjectStatusses(string $projectUid)
    {
        return apiResponse($this->service->getProjectStatusses($projectUid));
    }

    /**
     * Function to change status of selected project
     *
     * @param ChangeStatus $request
     * @param string $projectUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeStatus(ChangeStatus $request, string $projectUid)
    {
        return apiResponse($this->service->changeStatus($request->toArray(), $projectUid));
    }

    /**
     * Get PIC for request team member component
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTargetPicsAndTaskList(string $projectUid)
    {
        return apiResponse($this->service->getTargetPicsAndTaskList($projectUid));
    }

    /**
     * Function to get pic teams for request team member
     *
     * @param string $projectUid
     * @param string $picUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPicTeams(string $projectUid, string $picUid)
    {
        return apiResponse($this->service->getPicTeams($projectUid, $picUid));
    }

    public function loadTeamMember(LoanTeamMember $request, string $projectUid)
    {
        return apiResponse($this->service->loanTeamMember($request->validated(), $projectUid));
    }

    public function uploadShowreels(UploadShowreels $request, string $projectUid)
    {
        return apiResponse($this->service->uploadShowreels($request->validated(), $projectUid));
    }

    public function completeProject(CompleteProject $request, string $projectUid)
    {
        return apiResponse($this->service->completeProject($request->validated(), $projectUid));
    }

    public function getTaskTeamForReview(string $projectUid)
    {
        return apiResponse($this->service->getTaskTeamForReview($projectUid));
    }

    public function assignVJ(\Modules\Production\Http\Requests\Project\AssignVj $request, string $projectUid)
    {
        return apiResponse($this->service->assignVJ($request->validated(), $projectUid));
    }

    /**
     * Function to get all item for final check
     *
     * @param string $projectUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function prepareFinalCheck(string $projectUid)
    {
        return apiResponse($this->service->prepareFinalCheck($projectUid));
    }

    public function readyToGo(string $projectUid)
    {
        return apiResponse($this->service->readyToGo($projectUid));
    }

    public function returnEquipment(\Modules\Production\Http\Requests\Project\ReturnEquipment $request, string $projectUid)
    {
        return apiResponse($this->service->returnEquipment($projectUid, $request->validated()));
    }

    public function downloadReferences(string $projectUid)
    {
        $references = $this->service->downloadReferences($projectUid);
        $name = str_replace(' ', '', $references['project']->name);
        $name .= "_references.zip";

        return \STS\ZipStream\Facades\Zip::create("{$name}", $references['files']);
    }

    public function getAllSchedulerProjects(string $projectUid)
    {
        return apiResponse($this->service->getAllSchedulerProjects($projectUid));
    }

    public function getPicScheduler(string $projectUid)
    {
        return apiResponse($this->service->getPicScheduler($projectUid));
    }

    public function assignPic(\Modules\Production\Http\Requests\Project\AssignPic $request, string $projectUid)
    {
        return $this->service->assignPic($projectUid, $request->validated());
    }

    public function subtitutePic(\Modules\Production\Http\Requests\Project\SubtitutePic $request, string $projectUid)
    {
        return $this->service->subtitutePic($projectUid, $request->validated());
    }

    public function getPicForSubtitute(string $projectUid)
    {
        return apiResponse($this->service->getPicForSubtitute($projectUid));
    }

    public function downloadProofOfWork(string $projectUid, int $proofOfWorkId)
    {
        $references = $this->service->downloadProofOfWork($projectUid, $proofOfWorkId);
        $name = str_replace(' ', '', $references['task']->name);
        $name .= "_proof_of_work.zip";

        return \STS\ZipStream\Facades\Zip::create("{$name}", $references['files']);
    }

    public function downloadReviseMedia(string $projectUid, int $reviseId)
    {
        $references = $this->service->downloadReviseMedia($projectUid, $reviseId);
        $name = str_replace(' ', '', $references['task']->name);
        $name .= "_revise.zip";

        return \STS\ZipStream\Facades\Zip::create("{$name}", $references['files']);
    }

    /**
     * Get all projects for file manager
     */
    public function getProjectsFolder()
    {
        return apiResponse($this->service->getProjectsFolder());
    }

    public function getProjectYears()
    {
        return apiResponse($this->service->getprojectyears());
    }

    public function getProjectFolderDetail()
    {
        return apiResponse($this->service->getProjectFolderDetail());
    }

    public function cancelProject(\Modules\Production\Http\Requests\Project\CancelProject $request, string $projectUid)
    {
        return apiResponse($this->service->cancelProject($request->validated(), $projectUid));
    }

    public function initEntertainmentTeam()
    {
        return apiResponse($this->service->initEntertainmentTeam());
    }

    public function requestEntertainment(\Modules\Production\Http\Requests\Project\RequestEntertainment $request, string $projectUid)
    {
        return apiResponse($this->service->requestEntertainment($request->validated(), $projectUid));
    }

    public function getEmployeeTaskList(string $projectUid, int $employeeId)
    {
        return apiResponse($this->service->getEmployeeTaskList($projectUid, $employeeId));
    }

    /**
     * Store song for selected project
     *
     * @param RequestSong $request
     * @param string $projectUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeSongs(RequestSong $request, string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->storeSongs($request->validated(), $projectUid));
    }

    /**
     * Change worker song
     *
     * @param SubtituteWorkerSong $request
     * @param string $projectUid
     * @param string $songUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function subtituteSongPic(SubtituteWorkerSong $request, string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->subtituteSongPic($request->validated(), $projectUid, $songUid));
    }

    /**
     * Start work on selected song task
     *
     * @param string $projectUid
     * @param string $songUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function startWorkOnSong(string $projectUid, string $songUid)
    {
        return apiResponse($this->service->startWorkOnSong($projectUid, $songUid));
    }

    /**
     * Function to bulk assign
     *
     * @param BulkAssignSong $request
     * @param string $projectUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkAssignWorkerForSong(BulkAssignSong $request, string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->bulkAssignWorkerForSong($request->validated(), $projectUid));
    }

    /**
     * Function to get detail of song
     *
     * @param string $projectUid
     * @param string $songUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailSong(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->detailSong($projectUid, $songUid));
    }

    /**
     * Function to update song
     *
     * @param UpdateSong $request
     * @param string $projectUid
     * @param string $songUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSong(UpdateSong $request, string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->updateSong($request->validated(), $projectUid, $songUid));
    }

    /**
     * Request changes is being approved
     *
     * @param string $projectUid
     * @param string $songUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmEditSong(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->confirmEditSong($projectUid, $songUid));
    }

    /**
     * Delete song
     *
     * @param string $projectUid
     * @param string $songUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmDeleteSong(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->confirmDeleteSong($projectUid, $songUid));
    }

    /**
     * Function to delete single song
     *
     * @param string $projectUid
     * @param string $songUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSong(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->deleteSong($projectUid, $songUid));
    }

    /**
     * Function get get all entertainment list with the workload
     * 
     * @param $projectUid
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function entertainmentListMember(string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->entertainmentListMember($projectUid));
    }

    /**
     * Get entertainment wokrload detail
     *
     * @param string $projectUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function entertainmentMemberWorkload(string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->entertainmentMemberWorkload($projectUid));
    }

    /**
     * Distribute song to selected player
     * One song one player
     *
     * @param DistributeSong $request
     * @param string $projectUid
     * @param string $songUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function distributeSong(DistributeSong $request, string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->distributeSong(
            payload: $request->validated(),
            projectUid: $projectUid,
            songUid: $songUid
        ));
    }

    /**
     * Function to reject request edit song
     * Can be done by PM entertainment
     *
     * @param RejectEditSong $request
     * @param string $projectUid
     * @param string $songUid
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectEditSong(RejectEditSong $request, string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->rejectEditSong($request->validated(), $projectUid, $songUid));
    }
}

