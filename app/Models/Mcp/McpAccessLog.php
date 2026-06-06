<?php

namespace App\Models\Mcp;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'user_id',
        'user_email',
        'user_name',
        'method',
        'route_uri',
        'route_name',
        'status_code',
        'is_success',
        'parameters',
        'response_message',
        'ip',
        'user_agent',
        'duration_ms',
        'accessed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_success' => 'boolean',
            'parameters' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'accessed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
