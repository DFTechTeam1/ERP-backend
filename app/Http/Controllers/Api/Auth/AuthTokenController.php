<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\Auth\RefreshTokenInvalid;
use App\Http\Controllers\Controller;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthTokenController extends Controller
{
    public function __construct(
        private TokenService $tokenService,
        private RefreshTokenService $refreshTokenService,
    ) {}

    /**
     * Exchange a (cookie or body) refresh token for a fresh access token,
     * rotating the refresh token. Returns 401 on any invalid/expired/revoked
     * token; theft detection (family revocation) is handled by the service.
     */
    public function refresh(Request $request): JsonResponse
    {
        $raw = $request->cookie((string) config('jwt.refresh_cookie'))
            ?? $request->input('refresh_token');

        if (! $raw) {
            return $this->unauthenticated();
        }

        try {
            $issued = $this->refreshTokenService->rotate(
                raw: $raw,
                userAgent: $request->userAgent(),
                ip: $request->ip(),
            );
        } catch (RefreshTokenInvalid $e) {
            return $this->unauthenticated()
                ->withCookie($this->refreshTokenService->forgetCookie());
        }

        $accessToken = $this->tokenService->issueAccessToken($issued['user']);

        return apiResponse(
            generalResponse(
                'Success',
                false,
                ['access_token' => $accessToken],
            ),
        )->withCookie(
            $this->refreshTokenService->makeCookie($issued['raw'], (bool) $issued['model']->remember)
        );
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}
