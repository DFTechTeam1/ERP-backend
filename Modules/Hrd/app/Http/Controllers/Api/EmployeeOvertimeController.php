<?php

namespace Modules\Hrd\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrd\Services\EmployeeOvertimeService;

class EmployeeOvertimeController extends Controller
{
    public function __construct(
        private readonly EmployeeOvertimeService $service
    ) {}

    public function resync(): JsonResponse
    {
        return apiResponse($this->service->fetchFromGreatday('DO250015'));
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('hrd::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('hrd::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('hrd::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('hrd::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
