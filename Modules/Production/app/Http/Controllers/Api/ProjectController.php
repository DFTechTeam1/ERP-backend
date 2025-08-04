<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Http\Requests\Deals\CancelProjectDeal;
use Modules\Production\Http\Requests\Deals\NewQuotation;
use Modules\Production\Http\Requests\Project\BasicUpdate;
use Modules\Production\Http\Requests\Project\BulkAssignSong;
use Modules\Production\Http\Requests\Project\ChangeStatus;
use Modules\Production\Http\Requests\Project\ChangeTaskBoard;
use Modules\Production\Http\Requests\Project\CompleteProject;
use Modules\Production\Http\Requests\Project\Create;
use Modules\Production\Http\Requests\Project\CreateDescription;
use Modules\Production\Http\Requests\Project\CreateTask;
use Modules\Production\Http\Requests\Project\Deals\Customer\StoreCustomer;
use Modules\Production\Http\Requests\Project\DistributeModelerTask;
use Modules\Production\Http\Requests\Project\DistributeSong;
use Modules\Production\Http\Requests\Project\HoldTask;
use Modules\Production\Http\Requests\Project\LoanTeamMember;
use Modules\Production\Http\Requests\Project\ManualChangeTaskBoard;
use Modules\Production\Http\Requests\Project\MoreDetailUpdate;
use Modules\Production\Http\Requests\Project\RejectEditSong;
use Modules\Production\Http\Requests\Project\RequestSong;
use Modules\Production\Http\Requests\Project\ReviseTask;
use Modules\Production\Http\Requests\Project\SongReportAsDone;
use Modules\Production\Http\Requests\Project\SongRevise;
use Modules\Production\Http\Requests\Project\StoreReferences;
use Modules\Production\Http\Requests\Project\SubtituteWorkerSong;
use Modules\Production\Http\Requests\Project\UpdateDeadline;
use Modules\Production\Http\Requests\Project\UpdateSong;
use Modules\Production\Http\Requests\Project\UploadProofOfWork;
use Modules\Production\Http\Requests\Project\UploadShowreels;
use Modules\Production\Services\CustomerService;
use Modules\Production\Services\ProjectService;
use Modules\Production\Services\TestingService;

class ProjectController extends Controller
{
    private $service;

    private $testingService;

    private $customerService;

    private $projectDealService;

    public function __construct(
        ProjectService $projectService,
        TestingService $testingService,
        CustomerService $customerService,
        \Modules\Production\Services\ProjectDealService $projectDealService
    ) {
        $this->service = $projectService;

        $this->testingService = $testingService;

        $this->customerService = $customerService;

        $this->projectDealService = $projectDealService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->testingService->list(
            'id,uid,name,project_date,venue,event_type,collaboration,note,marketing_id,status,classification,led_area,led_detail,project_class_id,project_deal_id',
            '',
            [
                'marketing:id,name,employee_id',
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id',
                'marketings.marketing:id,name,employee_id',
                'projectClass:id,name,color',
                'vjs.employee:id,nickname',
                'equipments:id,project_id',
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
     * Check all project tasks before user complete the project
     */
    public function precheck(string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->precheck(projectUid: $projectUid));
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
     * @param  string  $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBasic(BasicUpdate $request, string $projectId)
    {
        return apiResponse($this->service->updateBasic($request->validated(), $projectId));
    }

    /**
     * Update more detail
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMoreDetail(MoreDetailUpdate $request, string $uid)
    {
        return apiResponse($this->service->updateMoreDetail($request->validated(), $uid));
    }

    /**
     * Store project references
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeReferences(StoreReferences $request, string $id)
    {
        return apiResponse($this->service->storeReferences($request->validated(), $id));
    }

    /**
     * Create new task on selected board
     *
     * @param  int  $boardId
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeTask(CreateTask $request, $boardId)
    {
        return apiResponse($this->service->storeTask($request->validated(), (int) $boardId));
    }

    /**
     * Distribute task to modeler teams
     */
    public function distributeModellerTask(DistributeModelerTask $request, string $projectUid, string $taskUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->distributeModellerTask($request->validated(), $projectUid, $taskUid));
    }

    /**
     * Delete selected task
     *
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
     * @param  string  $taskId
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeDescription(CreateDescription $request, $taskId)
    {
        return apiResponse($this->service->storeDescription($request->validated(), (string) $taskId));
    }

    /**
     * Get teams of selected project
     *
     * @param  string  $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectMembers($projectId, string $taskId)
    {
        return apiResponse($this->service->getProjectMembers((int) $projectId, $taskId));
    }

    /**
     * Assign members / employees to selected task
     *
     * @param  string  $taskId
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
     *
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
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeAllVJ(string $projectUid)
    {
        return apiResponse($this->service->removeAllVJ($projectUid));
    }

    /**
     * Store new request equipment
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestEquipment(\Modules\Production\Http\Requests\Project\RequestEquipment $request, string $projectId)
    {
        return apiResponse($this->service->requestEquipment($request->validated(), $projectId));
    }

    /**
     * List of project equipments
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listEquipment(string $projectId)
    {
        return apiResponse($this->service->listEquipment($projectId));
    }

    /**
     * Update selected equipment
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEquipment(\Modules\Production\Http\Requests\Project\UpdateEquipment $request, string $projectId)
    {
        return apiResponse($this->service->updateEquipment($request->validated(), $projectId));
    }

    /**
     * Cancel request equipment
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelRequestEquipment(Request $request, string $projectId)
    {
        return apiResponse($this->service->cancelRequestEquipment($request->toArray(), $projectId));
    }

    /**
     * Add project deadline
     *
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeTaskBoard(ChangeTaskBoard $request, string $projectId)
    {
        return apiResponse($this->service->changeTaskBoard($request->validated(), $projectId));
    }

    /**
     * Change board of task (When user move a task)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function manualMoveBoard(ChangeTaskBoard $request, string $projectId)
    {
        return apiResponse($this->service->manualMoveBoard($request->validated(), $projectId));
    }

    /**
     * * Change board of task (When user move a task)
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

    public function getTaskStatus()
    {
        return apiResponse($this->service->getTaskStatus());
    }

    /**
     * Function to change status of selected project
     *
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
        $name .= '_references.zip';

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
        $name .= '_proof_of_work.zip';

        return \STS\ZipStream\Facades\Zip::create("{$name}", $references['files']);
    }

    public function downloadReviseMedia(string $projectUid, int $reviseId)
    {
        $references = $this->service->downloadReviseMedia($projectUid, $reviseId);
        $name = str_replace(' ', '', $references['task']->name);
        $name .= '_revise.zip';

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
     */
    public function storeSongs(RequestSong $request, string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->storeSongs($request->validated(), $projectUid));
    }

    /**
     * Change worker song
     */
    public function subtituteSongPic(SubtituteWorkerSong $request, string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->subtituteSongPic($request->validated(), $projectUid, $songUid));
    }

    /**
     * Start work on selected song task
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function startWorkOnSong(string $projectUid, string $songUid)
    {
        return apiResponse($this->service->startWorkOnSong($projectUid, $songUid));
    }

    /**
     * Function to bulk assign
     */
    public function bulkAssignWorkerForSong(BulkAssignSong $request, string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->bulkAssignWorkerForSong($request->validated(), $projectUid));
    }

    /**
     * Function to get detail of song
     */
    public function detailSong(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->detailSong($projectUid, $songUid));
    }

    /**
     * Function to update song
     */
    public function updateSong(UpdateSong $request, string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->updateSong($request->validated(), $projectUid, $songUid));
    }

    /**
     * Store the result of work
     */
    public function songReportAsDone(SongReportAsDone $request, string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->songReportAsDone($request->validated(), $projectUid, $songUid));
    }

    /**
     *  Here we'll remove pic from selected song
     * Step to produce:
     * 1. Delete pic from entertainment_task_song table
     */
    public function removePicSong(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->removePicSong($projectUid, $songUid));
    }

    /**
     * Approve JB by root, PM or director
     */
    public function songApproveWork(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->songApproveWork($projectUid, $songUid));
    }

    /**
     * Request changes is being approved
     */
    public function confirmEditSong(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->confirmEditSong($projectUid, $songUid));
    }

    /**
     * Delete song
     */
    public function confirmDeleteSong(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->confirmDeleteSong($projectUid, $songUid));
    }

    /**
     * Function to delete single song
     */
    public function deleteSong(string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->deleteSong($projectUid, $songUid));
    }

    /**
     * Function get get all entertainment list with the workload
     */
    public function entertainmentListMember(string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->entertainmentListMember($projectUid));
    }

    /**
     * Get entertainment wokrload detail
     */
    public function entertainmentMemberWorkload(string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->entertainmentMemberWorkload($projectUid));
    }

    /**
     * Distribute song to selected player
     * One song one player
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
     */
    public function rejectEditSong(RejectEditSong $request, string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->rejectEditSong($request->validated(), $projectUid, $songUid));
    }

    /**
     * Revise JB
     */
    public function songRevise(SongRevise $request, string $projectUid, string $songUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->songRevise($request->validated(), $projectUid, $songUid));
    }

    /**
     * Complete all unfinished task
     */
    public function completeUnfinishedTask(string $projectUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->completeUnfinishedTask($projectUid));
    }

    public function filterTasks(Request $request, string $projectUid)
    {
        return apiResponse($this->service->filterTasks($request->all(), $projectUid));
    }

    public function getEntertainmentSongWorkload() {}

    /**
     * Store new customer
     *
     * @param  StoreCustomer  $request  This will have structure:
     *                                  - string $name           Required
     *                                  - string $phone          Required
     *                                  - ?string $email         Nullable
     */
    public function storeCustomer(StoreCustomer $request): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->customerService->store($request->validated()));
    }

    /**
     * Get all customer list
     */
    public function getCustomer(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->customerService->getAll());
    }

    /**
     * Function to check the project is categorized as high_season or not
     */
    public function checkHighSeason(Request $request): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->checkHighSeason($request->all()));
    }

    /**
     * Get Calculation formula
     */
    public function getCalculationFormula(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->getCalculationFormula());
    }

    /**
     * Generate the available quotation number for the upcoming project deals
     */
    public function getQuotationNumber(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->getQuotationNumber());
    }

    /**
     * Create and create quotation for project deal
     * 
     * @param \Modules\Production\Http\Requests\Deals\Store $request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeProjectDeals(\Modules\Production\Http\Requests\Deals\Store $request)
    {
        $data = $request->validated();
        $data['quotation']['quotation_id'] = str_replace('#', '', $data['quotation']['quotation_id']);

        return apiResponse($this->service->storeProjectDeals($data));
    }

    /**
     * Create and create quotation for project deal
     * 
     * @param \Modules\Production\Http\Requests\Deals\Store $request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProjectDeal(\Modules\Production\Http\Requests\Deals\Store $request, string $projectDealUid)
    {
        $data = $request->validated();
        $data['quotation']['quotation_id'] = str_replace('#', '', $data['quotation']['quotation_id']);

        return apiResponse($this->service->updateProjectDeals($data, $projectDealUid));
    }

    /**
     * Return list of project deals
     * 
     * @return JsonResponse
     */
    public function listProjectDeals(): JsonResponse
    {
        return apiResponse($this->projectDealService->list(
            select: 'id,project_date,name,venue,city_id,collaboration,status,is_fully_paid',
            relation: [
                'marketings',
                'marketings.employee:id,nickname',
                'city:id,name',
                'transactions:id,project_deal_id,payment_amount,created_at',
                'latestQuotation',
                'finalQuotation',
                'firstTransaction',
                'unpaidInvoices:id,number,parent_number,project_deal_id,amount'
            ]
        ));
    }

    public function createNewQuotation(NewQuotation $request, string $projectDealId)
    {
        
    }

    /**
     * Publish project deal
     *
     * @param string $projectDealId
     * @param string $type
     * 
     * @return JsonResponse
     */
    public function publishProjectDeal(string $projectDealId, string $type): JsonResponse
    {
        return apiResponse($this->projectDealService->publishProjectDeal($projectDealId, $type));
    }

    /**
     * Get detail of project deals
     * Get all transactions and quotations
     * 
     * @return JsonResponse
     */
    public function detailProjectDeal(string $projectDealUid): JsonResponse
    {
        return apiResponse($this->projectDealService->detailProjectDeal(projectDealUid: $projectDealUid));
    }

    /**
     * Delete current project deal
     *
     * @param string $projectDealUid
     * @return JsonReponse
     */
    public function deleteProjectDeal(string $projectDealUid): JsonResponse
    {
        return apiResponse($this->projectDealService->delete(id: $projectDealUid));
    }

    /**
     * Adding more quotation in the selected project deal
     *
     * @param array $payload
     * @param string $projectDealUid
     * @return JsonResponse
     */
    public function addMoreQuotation(\Modules\Production\Http\Requests\Deals\MoreQuotation $request, string $projectDealUid): JsonResponse
    {
        return apiResponse($this->projectDealService->addMoreQuotation($request->validated(), $projectDealUid));
    }

    public function initProjectCount(): JsonResponse
    {
        return apiResponse($this->service->initProjectCount());
    }

    /**
     * Get report summary
     * 
     * @return JsonResponse
     */
    public function getReportSummary(): JsonResponse
    {
        return apiResponse($this->projectDealService->getProjectDealSummary());
    }

    public function cancelProjectDeal(CancelProjectDeal $request, string $projectDealUid): JsonResponse
    {
        return apiResponse($this->projectDealService->cancelProjectDeal(payload: $request->validated(), projectDealUid: $projectDealUid));
    }
}
