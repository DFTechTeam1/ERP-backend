<?php

namespace Modules\Company\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Company\Http\Requests\Branch\Create;
use Modules\Company\Http\Requests\Branch\Update;
use Modules\Company\Services\BranchService;

class BranchController extends Controller
{
    private $service;

    public function __construct(BranchService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list('id,name,short_name'));
    }

    /**
     * Store a newly created resource in storage.
     * 
     * @param Create $request
     * @return \Illuminate\Http\JsonResponse
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
     * Get all branches
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->getAll());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Update $request, $id)
    {
        return apiResponse($this->service->update($request->validated(), id: $id));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }

    public function bulkDelete(Request $request)
    {
        return apiResponse(
            payload: $this->service->bulkDelete(
                uids: collect($request->ids)->map(function ($item) {
                    return $item['uid'];
                })->toArray(),
                key: 'id'
            )
        );
    }
}
