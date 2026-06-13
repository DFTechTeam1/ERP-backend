<?php

namespace App\Services\Auth;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

/**
 * Issues and exposes verification config for RS256 access tokens.
 *
 * Laravel is the sole issuer. The signed JWT carries the user's roles and a
 * flat array of permission names so downstream services can authorize locally
 * without a callback. The token deliberately does NOT carry the menu (served
 * fresh from /api/menu) or any secret/PII — the payload is readable by the user.
 */
class TokenService
{
    private Configuration $config;

    public function __construct()
    {
        $this->config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText((string) config('jwt.private_key')),
            InMemory::plainText((string) config('jwt.public_key')),
        );
    }

    public function issueAccessToken(User $user): string
    {
        $now = new DateTimeImmutable;

        $roles = $user->getRoleNames()->values()->all();
        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        $token = $this->config->builder()
            ->withHeader('kid', config('jwt.kid'))
            ->issuedBy(config('jwt.issuer'))
            ->permittedFor(config('jwt.audience'))
            ->relatedTo((string) $user->id)
            ->identifiedBy((string) Str::uuid())
            ->issuedAt($now)
            ->expiresAt($now->modify('+'.(int) config('jwt.access_ttl').' minutes'))
            ->withClaim('roles', $roles)
            ->withClaim('permissions', $permissions)
            ->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
    }

    /**
     * Verification configuration (public key only is needed to verify).
     */
    public function configuration(): Configuration
    {
        return $this->config;
    }
}
