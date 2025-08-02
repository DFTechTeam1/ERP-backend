<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Http\Requests\DeadlineReason\Create;
use Modules\Production\Http\Requests\DeadlineReason\Update;
use Modules\Production\Services\DeadlineChangeReasonService;

class DeadlineChangeReasonController extends Controller
{
    private $service;

    public function __construct(DeadlineChangeReasonService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list(select: 'id,name'));
    }

    /**
     * Store a newly created resource in storage.
     * 
     * @return JsonResponse
     */
    public function store(Create $request): JsonResponse
    {
        return apiResponse($this->service->store(data: $request->validated()));
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Update the specified resource in storage.
     * 
     * @return JsonResponse
     */
    public function update(Update $request, $id): JsonResponse
    {
        return apiResponse($this->service->update(data: $request->validated(), id: $id));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        return apiResponse($this->service->delete(id: $id));
    }
}
