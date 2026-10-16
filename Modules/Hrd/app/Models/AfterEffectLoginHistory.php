<?php

namespace Modules\Hrd\Models;

use App\Models\User;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Hrd\Database\Factories\AfterEffectLoginHistoryFactory;

class AfterEffectLoginHistory extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'uid',
        'failure_reason',
        'os',
        'ae_version',
        'is_success',
        'login_at',
        'refresh_token_expires_at',
        'ip_address'
    ];

    // protected static function newFactory(): AfterEffectLoginHistoryFactory
    // {
    //     // return AfterEffectLoginHistoryFactory::new();
    // }
    //
    protected $casts = [
        'is_success' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
