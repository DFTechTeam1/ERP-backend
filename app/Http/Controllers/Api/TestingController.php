<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    public function testing()
    {
        $payload = [
            'pic_id' => 'cd3a3aef-1c21-48d7-8bdf-23a8c4920050',
            'teams' => ['8687c18c-fef4-43c7-b11e-a3018b5d3fd4'],
            'reason' => 'Pinjam sebentar',
            'task_id' => 'nullable',
        ];

        foreach ($payload['teams'] as $team) {
            $teamId = getIdFromUid($team, new \Modules\Hrd\Models\Employee());

            \Modules\Production\Jobs\RequestTeamMemberJob::dispatch(30, [
                'transferId' => 2,
                'team' => $teamId,
                'pic_id' => $payload['pic_id'],
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
