<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Http\Requests\Project\BasicUpdate;
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

class ProjectController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new ProjectService();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list(
            'id,uid,name,project_date,venue,event_type,collaboration,note,marketing_id,status,classification,led_area,led_detail',
            '',
            [
                'marketing:id,name,employee_id',
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id'
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

    public function getProjectStatusses()
    {
        return apiResponse($this->service->getProjectStatusses());
    }

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
        return apiResponse($this->service->loadTeamMember($request->validated(), $projectUid));
    }
}
