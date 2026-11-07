<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Data\Entertainment\BulkCreateSongData;
use App\Data\Entertainment\UpdateSongData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Services\EntertainmentService;

class EntertainmentController extends Controller
{
    public function __construct(
        private readonly EntertainmentService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(string $projectUid): JsonResponse
    {
        return apiResponse($this->service->list($projectUid));
    }

    public function createSong(BulkCreateSongData $payload, string $projectUid): JsonResponse
    {
        return apiResponse($this->service->createSong($payload, $projectUid));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        return response()->json([]);
    }

    /**
     * Show the specified resource.
     */
    public function show(string $projectUid, string $songUid): JsonResponse
    {
        return apiResponse($this->service->detail($projectUid, $songUid));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSongData $request, string $projectUid, string $songUid): JsonResponse
    {
        return apiResponse($this->service->update($request, $projectUid, $songUid));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $projectUid, string $songUid): JsonResponse
    {
        return apiResponse($this->service->delete($projectUid, $songUid));
    }
}
