<?php

namespace App\Services\Auth;

use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Instant access-token revocation for stateless RS256 JWTs.
 *
 * The access token itself is unforgeable but not revocable — there's nothing
 * to "delete" server-side. To make logout take effect immediately across every
 * live session for a user, we record a "sessions killed at" moment per user
 * in Redis and gate every JWT verification on it:
 *
 *   token accepted  iff  token.iat  >=  killed_at(user)
 *
 * A fresh login issues a token whose `iat` is later than `killed_at`, so it
 * passes automatically — no bookkeeping needed on the login path.
 *
 * The Redis key TTL is set to at least the access-token TTL, so the entry
 * lives long enough to shoot down every token issued before the logout and
 * then evicts itself. Keeps the store bounded.
 */
class AccessTokenRevocationService
{
    private const KEY_PREFIX = 'access_token:kill:';

    /**
     * Mark every access token this user was issued BEFORE now as dead.
     * Called from logout — the effect is felt on the very next JWT verify.
     */
    public function revokeAllForUser(int $userId): void
    {
        Cache::put(
            self::key($userId),
            now()->getTimestamp(),
            // Comfortably longer than the longest access token still in the
            // wild. Access-token TTL is configured in minutes; double it, add
            // a small buffer, and cap at 24h for sanity.
            $this->cacheTtlSeconds(),
        );
    }

    /**
     * True if this token was issued before the user's most recent logout —
     * i.e. it should be rejected even though the signature and expiry are
     * still valid.
     */
    public function wasIssuedBeforeRevocation(int $userId, DateTimeInterface $issuedAt): bool
    {
        $killedAt = Cache::get(self::key($userId));
        if ($killedAt === null) {
            return false;
        }

        return $issuedAt->getTimestamp() < (int) $killedAt;
    }

    private static function key(int $userId): string
    {
        return self::KEY_PREFIX.$userId;
    }

    private function cacheTtlSeconds(): int
    {
        $accessTtlMinutes = (int) config('jwt.access_ttl', 30);
        $ttl = max(($accessTtlMinutes * 60) * 2, 300);

        return min($ttl, 86_400);
    }
}
