<?php

namespace Modules\Company\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Company\Http\Requests\ProjectClass\Create;
use Modules\Company\Http\Requests\ProjectClass\Update;
use Modules\Company\Services\ProjectClassService;

class ProjectClassController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new ProjectClassService();
    }

    public function getAll()
    {
        return apiResponse($this->service->getAll());
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list());
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
        //

        return response()->json([]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Update $request, $id)
    {
        return apiResponse($this->service->update($request->validated(), 'dummy', 'id = ' . $id));
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
     * Delete bulk data
     */
    public function bulkDelete(Request $request)
    {
        return apiResponse($this->service->bulkDelete(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }
}
