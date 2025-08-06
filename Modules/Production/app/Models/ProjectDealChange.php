<?php

namespace Modules\Production\Models;

use App\Enums\Production\ProjectDealChangeStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Modules\Production\Database\Factories\ProjectDealChangeFactory;

class ProjectDealChange extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_deal_id',
        'detail_changes',
        'requested_by',
        'requested_at',
        'approval_by',
        'approval_at',
        'rejected_by',
        'rejected_at',
        'status'
    ];

    protected static function newFactory(): ProjectDealChangeFactory
    {
        return ProjectDealChangeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => ProjectDealChangeStatus::class
        ];
    }

    public function detailChanges(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? json_decode($value, true) : null,
            set: fn($value) => $value ? json_encode($value) : null
        );
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function projectDeal(): BelongsTo
    {
        return $this->belongsTo(ProjectDeal::class, 'project_deal_id');
    }
}
