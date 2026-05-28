<?php

namespace App\Models\Mcp;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthAuthCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'user_id',
        'scope',
        'code_challenge',
        'expires_at',
    ];

    public function scopeNotExpired(Builder $query)
    {
        return $query->where('expires_at', '>', now());
    }
}
