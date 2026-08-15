<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\AccessTokenRevocationService;
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
 * Verifies the centralized RS256 access token locally (no callback) and sets
 * the authenticated user. Mirrors how the non-Laravel services verify.
 */
class AuthenticateWithAccessToken
{
    public function __construct(
        private TokenService $tokenService,
        private AccessTokenRevocationService $revocationService,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $jwt = $request->bearerToken();

        if (! $jwt) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

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

            assert($token instanceof UnencryptedToken);

            $config->validator()->assert(
                $token,
                new SignedWith($config->signer(), $config->verificationKey()),
                new IssuedBy(config('jwt.issuer')),
                new PermittedFor(config('jwt.audience')),
                new LooseValidAt($clock),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $user = User::find($token->claims()->get('sub'));

        if (! $user) {
            return response()->json(['message' => 'User not found'], 401);
        }

        // Server-side revocation gate: even a cryptographically valid,
        // unexpired token is rejected if the user has logged out since it
        // was issued. Makes logout instant across every live session.
        $issuedAt = $token->claims()->get('iat');
        if ($issuedAt && $this->revocationService->wasIssuedBeforeRevocation((int) $user->id, $issuedAt)) {
            return response()->json(['message' => 'Session revoked'], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
