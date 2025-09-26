<?php

namespace Modules\Development\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Development\Http\Requests\DevelopmentProject\Task\AssignMember;
use Modules\Development\Http\Requests\DevelopmentProject\Task\ReviseTask;
use Modules\Development\Http\Requests\DevelopmentProject\Task\StoreReference;
use Modules\Development\Http\Requests\DevelopmentProject\Task\SubmitProof;
use Modules\Development\Http\Requests\DevelopmentProject\Task\TaskAttachment;
use Modules\Development\Http\Requests\DevelopmentProject\Task\UpdateTaskDeadline;
use Modules\Development\Http\Requests\DevelopmentProject\Update;
use Modules\Development\Services\DevelopmentProjectService;

class DevelopmentProjectController extends Controller
{
    protected DevelopmentProjectService $developmentProjectService;

    public function __construct(
        DevelopmentProjectService $developmentProjectService
    ) {
        $this->developmentProjectService = $developmentProjectService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->developmentProjectService->list(
            relation: [
                'pics:id,development_project_id,employee_id',
                'pics.employee:id,nickname',
                'tasks:id,development_project_id',
            ],
            select: 'id,uid,name,description,status,project_date,created_by'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\Modules\Development\Http\Requests\DevelopmentProject\Create $request)
    {
        return apiResponse($this->developmentProjectService->store(data: $request->validated()));
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return apiResponse($this->developmentProjectService->edit(uid: $id));
    }

    public function detail(string $projectUid)
    {
        return apiResponse($this->developmentProjectService->show(uid: $projectUid));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Update $request, $id)
    {
        return apiResponse($this->developmentProjectService->update(id: $id, data: $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        return apiResponse($this->developmentProjectService->delete(projectUid: $id));
    }

    /**
     * Create a new task for the specified project.
     */
    public function createTask(\Modules\Development\Http\Requests\DevelopmentProject\Task\Create $request, string $projectUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->storeTask(payload: $request->validated(), projectUid: $projectUid));
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateProjectBoards(string $projectUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->updateProjectBoards(projectUid: $projectUid));
    }

    /**
     * Delete task attachments
     */
    public function deleteTaskAttachment(string $projectUid, string $taskUid, string $attachmentId): JsonResponse
    {
        return apiResponse($this->developmentProjectService->deleteTaskAttachment(projectUid: $projectUid, taskUid: $taskUid, attachmentId: $attachmentId));
    }

    /**
     * Remove members from a task.
     */
    public function addTaskMember(AssignMember $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->addTaskMember(payload: $request->validated(), taskUid: $taskUid));
    }

    /**
     * Delete a task.
     */
    public function deleteTask(string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->deleteTask(taskUid: $taskUid));
    }

    /**
     * Approve a task.
     */
    public function approveTask(string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->approveTask(taskUid: $taskUid));
    }

    /**
     * Hold a task.
     */
    public function holdTask(string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->holdTask(taskUid: $taskUid));
    }

    /**
     * Start a task after it has been put on hold.
     */
    public function startTaskAfterHold(string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->startTaskAfterHold(taskUid: $taskUid));
    }

    /**
     * Submit task proofs.
     *
     * @param  array  $payload
     * @return array
     */
    public function submitTaskProofs(SubmitProof $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->submitTaskProofs(payload: $request->validated(), taskUid: $taskUid));
    }

    /**
     * Complete a task.
     */
    public function completeTask(string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->completeTask(taskUid: $taskUid));
    }

    /**
     * Revise a task.
     *
     * @param  Request  $request
     */
    public function reviseTask(ReviseTask $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->reviseTask($request->validated(), $taskUid));
    }

    public function moveBoardId(string $taskUid, int $boardId): JsonResponse
    {
        return apiResponse($this->developmentProjectService->moveBoardId(taskUid: $taskUid, boardId: $boardId));
    }

    /**
     * Store project references.
     */
    public function storeReferences(StoreReference $request, string $projectUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->storeReferences($request->validated(), $projectUid));
    }

    /**
     * Delete a project reference.
     *
     * @param  string  $taskUid
     */
    public function deleteReference(string $projectUid, int $referenceId): JsonResponse
    {
        return apiResponse($this->developmentProjectService->deleteReference($projectUid, $referenceId));
    }

    /**
     * Get related tasks for a specific project.
     */
    public function getRelatedTask(string $projectUid, string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->getRelatedTask($projectUid, $taskUid));
    }

    public function storeAttachments(TaskAttachment $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->storeAttachments($request->validated(), $taskUid));
    }

    public function updateTaskDeadline(UpdateTaskDeadline $request, string $taskUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->updateTaskDeadline($request->validated(), $taskUid));
    }

    public function completeProject(string $projectUid): JsonResponse
    {
        return apiResponse($this->developmentProjectService->completeProject(projectUid: $projectUid));
    }
}
