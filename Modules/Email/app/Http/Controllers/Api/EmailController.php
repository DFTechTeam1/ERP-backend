<?php

namespace Modules\Email\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Email\Data\Notification\SendEmailData;
use Modules\Email\Http\Requests\SendEmailRequest;
use Modules\Email\Services\EmailService;

class EmailController extends Controller
{
    public function __construct(
        private EmailService $emailService
    )
    {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('email::index');
    }

    public function send(SendEmailRequest $request): JsonResponse
    {
        $data = SendEmailData::fromRequest($request);

        return apiResponse($this->emailService->send(payload: $data));
    }
}
