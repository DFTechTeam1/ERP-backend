<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \Modules\Production\Http\Requests\RejectTeamRequest;

class TeamTransferController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new \Modules\Production\Services\TransferTeamMemberService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list());
    }

    public function cancelRequest(Request $request)
    {
        return apiResponse($this->service->cancelRequest($request->toArray()));
    }

    public function approveRequest(string $transferUid, string $deviceAction)
    {
        return apiResponse($this->service->approveRequest($transferUid, $deviceAction));
    }

    public function completeRequest(string $transferUid)
    {
        return apiResponse($this->service->completeRequest($transferUid));
    }

    public function rejectRequest(RejectTeamRequest $request, string $transferUid)
    {
        return apiResponse($this->service->rejectRequest($request->validated(), $transferUid));
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
    public function destroy(string $transferUid)
    {
        return apiResponse($this->service->delete($transferUid));
    }

    public function getMembersToLend(string $transferUid, string $employeeUid)
    {
        return apiResponse($this->service->getMembersToLend($transferUid, $employeeUid));
    }

    public function chooseTeam(\Modules\Production\Http\Requests\Project\ChooseTeamRequestMember $request, string $transferUid)
    {
        return apiResponse($this->service->chooseTeam($request->validated(), $transferUid));
    }
}
