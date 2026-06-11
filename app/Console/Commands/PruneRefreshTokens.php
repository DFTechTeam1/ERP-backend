<?php

namespace App\Console\Commands;

use App\Models\RefreshToken;
use Illuminate\Console\Command;

class PruneRefreshTokens extends Command
{
    /**
     * @var string
     */
    protected $signature = 'auth:prune-refresh-tokens';

    /**
     * @var string
     */
    protected $description = 'Delete refresh tokens whose expiry is well past (cleanup)';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('jwt.prune_after'));

        $deleted = RefreshToken::query()
            ->where('expires_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} expired refresh token(s).");

        return self::SUCCESS;
    }
}
