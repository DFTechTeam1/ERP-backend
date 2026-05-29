<?php

namespace Modules\Hrd\Models;

use App\Enums\Whatsapp\GroupTargetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Hrd\Database\Factories\WhatsappGroupFactory;

class WhatsappGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'community_id',
        'employee_id',
        'group_id',
        'group_name',
        'invitation_link',
        'target_type',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => GroupTargetType::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(WhatsappCommunity::class, 'community_id');
    }

    // protected static function newFactory(): WhatsappGroupFactory
    // {
    //     // return WhatsappGroupFactory::new();
    // }
}
