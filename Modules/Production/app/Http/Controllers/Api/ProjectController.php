<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Production\Http\Requests\Project\BasicUpdate;
use Modules\Production\Http\Requests\Project\Create;
use Modules\Production\Http\Requests\Project\CreateDescription;
use Modules\Production\Http\Requests\Project\CreateTask;
use Modules\Production\Http\Requests\Project\MoreDetailUpdate;
use Modules\Production\Http\Requests\Project\StoreReferences;
use Modules\Production\Services\ProjectService;

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
    public function updateBasic(BasicUpdate $request, string $uid)
    {
        return apiResponse($this->service->updateBasic($request->validated(), $uid));
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
    public function getProjectMembers($projectId)
    {
        return apiResponse($this->service->getProjectMembers((int) $projectId));
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
}
