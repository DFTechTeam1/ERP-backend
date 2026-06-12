<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Psr\Clock\ClockInterface;

/**
 * Staged dual-accept guard for the centralized-auth rollout.
 *
 * Accepts EITHER the new centralized RS256 access token (verified locally, the
 * same contract every other service implements) OR the legacy Sanctum personal
 * access token. This lets routes migrate to the centralized token without a
 * flag-day cutover: the frontend sends the RS256 token while any remaining
 * Sanctum-token callers keep working. Remove the Sanctum fallback in the final
 * cutover once every consumer sends the access token.
 */
class AuthenticateWithAccessTokenOrSanctum
{
    public function __construct(private TokenService $tokenService) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $jwt = $request->bearerToken();

        if ($jwt && ($user = $this->resolveFromAccessToken($jwt)) !== null) {
            Auth::setUser($user);

            return $next($request);
        }

        $sanctumUser = Auth::guard('sanctum')->user();

        if ($sanctumUser !== null) {
            Auth::setUser($sanctumUser);

            return $next($request);
        }

        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    /**
     * Validate the bearer token as a centralized RS256 access token. Returns the
     * matching user, or null when the token is not a valid access token (e.g. it
     * is a Sanctum token, malformed, or expired) so the caller can fall back.
     */
    private function resolveFromAccessToken(string $jwt): ?User
    {
        $config = $this->tokenService->configuration();

        $clock = new class implements ClockInterface
        {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable;
            }
        };

        try {
            $token = $config->parser()->parse($jwt);

            if (! $token instanceof UnencryptedToken) {
                return null;
            }

            $config->validator()->assert(
                $token,
                new SignedWith($config->signer(), $config->verificationKey()),
                new IssuedBy(config('jwt.issuer')),
                new PermittedFor(config('jwt.audience')),
                new LooseValidAt($clock),
            );
        } catch (\Throwable $e) {
            return null;
        }

        return User::find($token->claims()->get('sub'));
    }
}
