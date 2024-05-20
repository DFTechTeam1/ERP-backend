<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Production\Http\Requests\Project\BasicUpdate;
use Modules\Production\Http\Requests\Project\Create;
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
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }
}
