<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;

class ServiceTokenController extends Controller
{
    public function __construct(private TokenService $tokenService) {}

    /**
     * Issue a short-lived centralized RS256 access token for the configured
     * service account.
     *
     * Reached only through the `internal.service` HMAC middleware (shared
     * INTERNAL_SERVICE_SECRET), so no password is exchanged. Used by
     * erp-backend-node to authenticate downstream calls (e.g. erp-report's
     * /pic-assignment/queue) that now verify the centralized token. The minted
     * identity is fixed by config — the caller cannot request a token for an
     * arbitrary user — so a leaked request body can't escalate privileges.
     */
    public function issue(): JsonResponse
    {
        $email = config('jwt.service_account_email');

        if (! $email) {
            return apiResponse(generalResponse(
                message: 'Service account email is not configured (JWT_SERVICE_ACCOUNT_EMAIL).',
                error: true,
                code: 500,
            ));
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return apiResponse(generalResponse(
                message: 'Service account user not found.',
                error: true,
                code: 404,
            ));
        }

        $accessToken = $this->tokenService->issueAccessToken($user);

        return apiResponse(generalResponse(
            message: 'Success',
            error: false,
            data: ['access_token' => $accessToken],
            code: 200,
        ));
    }
}
