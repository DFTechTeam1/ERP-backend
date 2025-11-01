<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Http\Requests\Deals\AddInteractive;
use Modules\Production\Http\Requests\Interactive\AssignPic;
use Modules\Production\Http\Requests\Interactive\ChangeStatus;
use Modules\Production\Http\Requests\Interactive\SearchTask;
use Modules\Production\Http\Requests\Interactive\StoreReference;
use Modules\Production\Http\Requests\Interactive\SubstitutePic;
use Modules\Production\Http\Requests\Interactive\Task\AssignMember;
use Modules\Production\Http\Requests\Interactive\Task\HoldTask;
use Modules\Production\Http\Requests\Interactive\Task\ReviseTask;
use Modules\Production\Http\Requests\Interactive\Task\StoreDescription;
use Modules\Production\Http\Requests\Interactive\Task\StoreTask;
use Modules\Production\Http\Requests\Interactive\Task\SubmitProof;
use Modules\Production\Http\Requests\Interactive\Task\UpdateTaskDeadline;
use Modules\Production\Services\InteractiveProjectService;
use Modules\Production\Services\ProjectDealService;

class InteractiveController extends Controller
{
    private InteractiveProjectService $service;

    private ProjectDealService $projectDealService;

    public function __construct(
        InteractiveProjectService $service,
        ProjectDealService $projectDealService
    ) {
        $this->service = $service;
        $this->projectDealService = $projectDealService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list(
            relation: [
                'pics:id,intr_project_id,employee_id',
                'pics.employee:id,name,email,nickname',
            ]
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AddInteractive $request, string $projectDealUid)
    {
        return apiResponse(
            $this->projectDealService->addInteractive(
                projectDealUid: $projectDealUid,
                payload: $request->validated()
            )
        );
    }

    /**
     * Approve interactive request
     *
     * If actorId is present in the request, it means the approval is done via email link
     * and we return a view. Otherwise, we return a JSON response for in-app approval
     *
     * @param  string  $requestId
     */
    public function approveInteractive($requestId): JsonResponse|\Illuminate\Contracts\View\View
    {
        $approve = $this->projectDealService->approveInteractiveRequest(requestId: $requestId);

        if (request('actorId')) {
            if ($approve['error']) {
                if ($approve['code'] == 500) {
                    return view('errors.alreadyProcessed');
                }
                abort(400);
            }

            return view('invoices.approved', [
                'title' => 'Approve Interactive',
                'message' => 'Interactive project approved successfully.',
            ]);
        } else {
            return apiResponse($approve);
        }
    }

    /**
     * Reject interactive request
     *
     * If actorId is present in the request, it means the rejection is done via email link
     * and we return a view. Otherwise, we return a JSON response for in-app rejection
     *
     * @param  string  $requestId
     */
    public function rejectInteractiveRequest($requestId): JsonResponse|\Illuminate\Contracts\View\View
    {
        $rejected = $this->projectDealService->rejectInteractiveRequest(requestId: $requestId);

        if (request('actorId')) {
            if ($rejected['error']) {
                if ($rejected['code'] == 500) {
                    return view('errors.alreadyProcessed');
                }

                abort(400);
            }

            return view('invoices.approved', [
                'title' => 'Reject Interactive',
                'message' => 'Interactive project rejected successfully.',
            ]);
        } else {
            return apiResponse($rejected);
        }
    }

    /**
     * Show the specified resource.
     */
    public function show(string $uid)
    {
        return apiResponse($this->service->show(uid: $uid));
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
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }

    public function getTeamList(): JsonResponse
    {
        return apiResponse($this->service->getTeamList());
    }

    /**
     * Create a new task for the specified project.
     */
    public function storeTask(StoreTask $request, string $projectUid): JsonResponse
    {
        return apiResponse($this->service->storeTask(payload: $request->validated(), projectUid: $projectUid));
    }

    /**
     * Remove members from a task.
     */
    public function addTaskMember(AssignMember $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->service->addTaskMember(payload: $request->validated(), taskUid: $taskUid));
    }

    /**
     * Approve a task.
     */
    public function approveTask(string $taskUid): JsonResponse
    {
        return apiResponse($this->service->approveTask(taskUid: $taskUid));
    }

    /**
     * Submit task proofs.
     *
     * @param  array  $payload
     * @return array
     */
    public function submitTaskProofs(SubmitProof $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->service->submitTaskProofs(payload: $request->validated(), taskUid: $taskUid));
    }

    /**
     * Complete a task.
     */
    public function completeTask(string $taskUid): JsonResponse
    {
        return apiResponse($this->service->completeTask(taskUid: $taskUid));
    }

    /**
     * Revise a task.
     *
     * @param  Request  $request
     */
    public function reviseTask(ReviseTask $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->service->reviseTask($request->validated(), $taskUid));
    }

    /**
     * Delete a task.
     */
    public function deleteTask(string $taskUid): JsonResponse
    {
        return apiResponse($this->service->deleteTask(taskUid: $taskUid));
    }

    public function downloadAttachment(string $taskUid, string $attachmentId)
    {
        return $this->service->downloadAttachment($taskUid, $attachmentId);
    }

    /**
     * Delete selected attachments
     */
    public function deleteTaskAttachment(string $interactiveUid, string $taskUid, string $imageId): JsonResponse
    {
        return apiResponse($this->service->deleteTaskAttachment(interactiveUid: $interactiveUid, taskUid: $taskUid, imageId: $imageId));
    }

    /**
     * Update task deadline
     */
    public function updateTaskDeadline(UpdateTaskDeadline $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->service->updateTaskDeadline($request->validated(), $taskUid));
    }

    /**
     * Hold a task.
     */
    public function holdTask(HoldTask $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->service->holdTask(payload: $request->validated(), taskUid: $taskUid));
    }

    /**
     * Start a task after it has been put on hold.
     */
    public function startTaskAfterHold(string $taskUid): JsonResponse
    {
        return apiResponse($this->service->startTaskAfterHold(taskUid: $taskUid));
    }

    /**
     * Store project references.
     */
    public function storeReferences(StoreReference $request, string $projectUid): JsonResponse
    {
        return apiResponse($this->service->storeReferences($request->validated(), $projectUid));
    }

    /**
     * Delete a project reference.
     *
     * @param  string  $taskUid
     */
    public function deleteReference(string $projectUid, string $referenceId): JsonResponse
    {
        return apiResponse($this->service->deleteReference($projectUid, $referenceId));
    }

    public function storeDescription(StoreDescription $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->service->storeDescription($request->validated(), $taskUid));
    }

    /**
     * Assign PIC to a project.
     */
    public function assignPicToProject(AssignPic $request, string $interactiveUid): JsonResponse
    {
        return apiResponse($this->service->assignPicToProject($request->validated(), $interactiveUid));
    }

    /**
     * Substitute current project PIC
     */
    public function substitutePicInProject(SubstitutePic $request, string $interactiveUid): JsonResponse
    {
        return apiResponse($this->service->substitutePicInProject($request->validated(), $interactiveUid));
    }

    /**
     * Get list of PIC
     *
     *
     * @return JsonResponse
     */
    public function getPicScheduler(string $interactiveUid)
    {
        return apiResponse($this->service->getPicScheduler($interactiveUid));
    }

    /**
     * Cancel an interactive project
     */
    public function cancelProject(string $interactiveUid): JsonResponse
    {
        return apiResponse($this->service->cancelProject(interactiveUid: $interactiveUid));
    }

    /**
     * Change status of an interactive project
     *
     * @param  Request  $request
     */
    public function changeStatus(ChangeStatus $request, string $interactiveUid): JsonResponse
    {
        return apiResponse($this->service->changeStatus($request->validated(), $interactiveUid));
    }

    /**
     * Filter tasks in an interactive project
     * 
     * @param SearchTask $request
     * @param string $interactiveUid
     * @return JsonResponse
     */
    public function filterTasks(SearchTask $request, string $interactiveUid): JsonResponse
    {
        return apiResponse($this->service->filterTasks(payload: $request->validated(), interactiveUid: $interactiveUid));
    }

    /**
     * Get PIC list for substitute
     * @param string $interactiveUid
     * @return JsonResponse
     */
    public function getPicForSubtitute(string $interactiveUid): JsonResponse
    {
        return apiResponse($this->projectDealService->getPicForSubtitute($interactiveUid));
    }
}
