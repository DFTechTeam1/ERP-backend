<?php

namespace Modules\Email\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Email\Enums\EmailType;
use Modules\Email\Services\EmailService;

class EmailController extends Controller
{
    public function __construct(
        private EmailService $emailService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('email::index');
    }

    protected function validateData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipientEmail' => 'required',
            'emailType' => [
                Rule::enum(EmailType::class),
                'required',
            ],
        ]);

        if ($validator->fails()) {
            return validationErrorResponse($validator->errors()->toArray());
        }

        return EmailType::from($request->emailType)->validatePayload($request->all());
    }

    public function send(Request $request): JsonResponse
    {
        $isNotValid = $this->validateData($request);

        if ($isNotValid) {
            return apiResponse($isNotValid);
        }

        $data = EmailType::from($request->emailType)->getTypeData($request->all());

        return apiResponse($this->emailService->send(recipientEmail: $request->recipientEmail, emailType: EmailType::from($request->emailType), payload: $data));
    }
}
