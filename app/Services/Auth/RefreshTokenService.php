<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\RefreshTokenInvalid;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Opaque, rotating refresh tokens with family-based theft detection.
 *
 * - The raw token is a random 64-char string returned to the client once; only
 *   its SHA-256 hash is stored.
 * - Every use rotates: the old row is revoked and a NEW row is inserted in the
 *   same family with a fresh (sliding) expiry, carrying the `remember` flag.
 * - Replaying an already-revoked token revokes the entire family (theft), unless
 *   it falls inside a short grace window (a benign near-simultaneous double
 *   refresh), in which case the family is left intact.
 */
class RefreshTokenService
{
    public function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }

    /**
     * Issue a brand-new refresh token (new family unless one is carried over).
     *
     * @return array{raw: string, model: RefreshToken, user: User}
     */
    public function issue(
        User $user,
        bool $remember,
        ?string $userAgent = null,
        ?string $ip = null,
        ?string $familyId = null
    ): array {
        $raw = Str::random(64);

        $model = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($raw),
            'family_id' => $familyId ?? (string) Str::uuid(),
            'remember' => $remember,
            'expires_at' => $this->resolveExpiry($remember),
            'user_agent' => $userAgent,
            'ip' => $ip,
        ]);

        return ['raw' => $raw, 'model' => $model, 'user' => $user];
    }

    /**
     * Validate + rotate a refresh token. Throws RefreshTokenInvalid on any
     * failure (unknown / expired / revoked / detected replay).
     *
     * @return array{raw: string, model: RefreshToken, user: User}
     */
    public function rotate(string $raw, ?string $userAgent = null, ?string $ip = null): array
    {
        // The rotation (revoke old + insert new) is atomic and serialized via a
        // row lock so two concurrent refreshes can't both succeed. A detected
        // replay is signalled out of the transaction so its family-revocation
        // side-effect commits independently rather than being rolled back by the
        // throw.
        $outcome = DB::transaction(function () use ($raw, $userAgent, $ip): array {
            $current = RefreshToken::query()
                ->where('token_hash', $this->hash($raw))
                ->lockForUpdate()
                ->first();

            if (! $current) {
                throw new RefreshTokenInvalid('Unknown refresh token');
            }

            if ($current->revoked_at !== null) {
                return ['replay' => $current];
            }

            if ($current->expires_at->isPast()) {
                throw new RefreshTokenInvalid('Refresh token expired');
            }

            $issued = $this->issue(
                user: $current->user,
                remember: (bool) $current->remember,
                userAgent: $userAgent,
                ip: $ip,
                familyId: $current->family_id,
            );

            $current->forceFill([
                'revoked_at' => now(),
                'replaced_by' => $issued['model']->id,
            ])->save();

            return ['issued' => $issued];
        });

        if (isset($outcome['replay'])) {
            $this->handleRevokedReplay($outcome['replay']);

            throw new RefreshTokenInvalid('Refresh token already used');
        }

        return $outcome['issued'];
    }

    /**
     * A replay of a revoked token is theft UNLESS it was rotated moments ago
     * (grace window) and its replacement is still alive — then it's a benign
     * near-simultaneous double refresh and the family is preserved.
     */
    private function handleRevokedReplay(RefreshToken $current): void
    {
        $graceCutoff = now()->subSeconds((int) config('jwt.refresh_grace_seconds'));

        $benignRace = $current->replaced_by !== null
            && $current->revoked_at !== null
            && $current->revoked_at->greaterThanOrEqualTo($graceCutoff);

        if ($benignRace) {
            return;
        }

        $this->revokeFamily($current->family_id);
    }

    public function revoke(string $raw): void
    {
        RefreshToken::query()
            ->where('token_hash', $this->hash($raw))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeFamily(string $familyId): void
    {
        RefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function resolveExpiry(bool $remember): Carbon
    {
        $days = $remember
            ? (int) config('jwt.refresh_ttl_remember')
            : (int) config('jwt.refresh_ttl');

        return now()->addDays($days);
    }

    /**
     * Build the httpOnly cookie that carries the raw refresh token.
     */
    public function makeCookie(string $raw, bool $remember): SymfonyCookie
    {
        $days = $remember
            ? (int) config('jwt.refresh_ttl_remember')
            : (int) config('jwt.refresh_ttl');

        return Cookie::make(
            name: (string) config('jwt.refresh_cookie'),
            value: $raw,
            minutes: $days * 24 * 60,
            path: (string) config('jwt.refresh_cookie_path'),
            domain: config('jwt.refresh_cookie_domain'),
            secure: (bool) config('jwt.refresh_cookie_secure'),
            httpOnly: true,
            raw: false,
            sameSite: (string) config('jwt.refresh_cookie_same_site'),
        );
    }

    /**
     * Expire the refresh cookie on the client (used on logout).
     */
    public function forgetCookie(): SymfonyCookie
    {
        return Cookie::forget(
            (string) config('jwt.refresh_cookie'),
            (string) config('jwt.refresh_cookie_path'),
            config('jwt.refresh_cookie_domain'),
        );
    }
}
