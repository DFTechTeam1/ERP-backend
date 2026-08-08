<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Data\Production\Entertainment\BulkUpdateGroupSongData;
use App\Data\Production\Entertainment\CreateJumpBackData;
use App\Data\Production\Entertainment\CreateSongData;
use App\Data\Production\Entertainment\UpdateSongData;
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
    public function store(Request $request) {}

    /**
     * Create new song for selected project
     */
    public function createSong(CreateSongData $request, string $projectUid): JsonResponse
    {
        return apiResponse($this->service->createSong($request, $projectUid));
    }

    /**
     * Update existing song for selected project
     */
    public function updateSong(UpdateSongData $request, string $projectUid, string $songUid): JsonResponse
    {
        return apiResponse($this->service->updateSong($request, $projectUid, $songUid));
    }

    public function bulkUpdateGroupSong(BulkUpdateGroupSongData $request, string $projectUid): JsonResponse
    {
        return apiResponse($this->service->bulkUpdateGroupSong($request, $projectUid));
    }

    public function deleteSingleSong(string $projectUid, string $groupUid, string $songUid): JsonResponse
    {
        return apiResponse($this->service->deleteSingleSong($projectUid, $groupUid, $songUid));
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

    public function deleteSong(string $projectUid, string $groupUid, string $songUid): JsonResponse
    {
        return apiResponse($this->service->deleteSingleSong($projectUid, $groupUid, $songUid));
    }

    public function createJumpBackTask(CreateJumpBackData $request, string $projectUid)
    {
        return apiResponse($this->service->createJumpBackTask($request, $projectUid));
    }

    public function listTask(string $projectUid)
    {
        return apiResponse($this->service->listTask($projectUid));
    }
}
