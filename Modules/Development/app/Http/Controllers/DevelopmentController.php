<?php

namespace Modules\Development\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Development\Services\DevelopmentProjectService;

class DevelopmentController extends Controller
{
    private DevelopmentProjectService $service;

    public function __construct(DevelopmentProjectService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('development::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('development::create');
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
        return view('development::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('development::edit');
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

    public function downloadAttachment(string $taskUid, string $attachmentId)
    {
        return $this->service->downloadAttachment($taskUid, $attachmentId);
    }
}
