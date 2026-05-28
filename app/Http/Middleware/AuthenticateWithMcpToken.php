<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Clock\ClockInterface;

class AuthenticateWithMcpToken
{
    public function handle(Request $request, Closure $next): mixed
    {
        $jwt = $request->bearerToken();

        if (! $jwt) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $publicKey = InMemory::file(storage_path('oauth/public.key'));

        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            $publicKey,
            $publicKey
        );

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

            $config->validator()->assert($token,
                new SignedWith($config->signer(), $config->verificationKey()),
                new IssuedBy(config('app.url')),
                new PermittedFor(config('mcp.server_url')),
                new StrictValidAt($clock),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $user = User::find($token->claims()->get('sub'));

        if (! $user) {
            return response()->json(['message' => 'User not found'], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
