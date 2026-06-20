<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Data\Production\Entertainment\CreateSongData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Services\EntertainmentService;

class EntertainmentController extends Controller
{
    public function __construct(
        public readonly EntertainmentService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(string $projectUid)
    {
        return apiResponse($this->service->list($projectUid));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('production::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        
    }

    public function createSong(CreateSongData $request, string $projectUid)
    {
        return apiResponse($this->service->createSong($request, $projectUid));
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('production::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('production::edit');
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

    public function deleteSong(string $projectUid, string $songUid): JsonResponse
    {
        return apiResponse($this->service->deleteSong($projectUid, $songUid));
    }
}
