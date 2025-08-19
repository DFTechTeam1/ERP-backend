<?php

namespace Modules\Development\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        //

        return response()->json([]);
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
        //

        return response()->json([]);
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
}
