<?php

namespace Modules\Development\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Development\Http\Requests\DevelopmentProject\Update;
use Modules\Development\Services\DevelopmentProjectService;

class DevelopmentProjectController extends Controller
{
    protected DevelopmentProjectService $developmentProjectService;

    public function __construct(
        DevelopmentProjectService $developmentProjectService
    )
    {
        $this->developmentProjectService = $developmentProjectService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->developmentProjectService->list());
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
        return apiResponse($this->developmentProjectService->show(uid: $id));
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
}
