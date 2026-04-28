<?php

namespace Modules\Email\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Email\Data\Notification\SendSlackMessageData;
use Modules\Email\Enums\SlackType;
use Modules\Email\Http\Requests\SendSlackMessageRequest;
use Modules\Email\Jobs\SendSlackJob;
use Modules\Email\Notifications\GlobalSlackNotification;
use Modules\Email\Services\SlackService;

class SlackController extends Controller
{
    public function __construct(
        public SlackService $service
    ) {}

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
    public function destroy($id)
    {
        //

        return response()->json([]);
    }

    public function send(SendSlackMessageRequest $request): JsonResponse
    {
        $data = SendSlackMessageData::from($request->validated());

        SendSlackJob::dispatch($data);

        return apiResponse(generalResponse(message: 'Slack message sent'));
    }
}
