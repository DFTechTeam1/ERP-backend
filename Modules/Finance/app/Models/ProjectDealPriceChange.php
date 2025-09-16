<?php

namespace Modules\Finance\Models;

use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Database\Factories\ProjectDealPriceChangeFactory;
use Modules\Production\Models\ProjectDeal;

// use Modules\Finance\Database\Factories\ProjectDealPriceChangeFactory;

class ProjectDealPriceChange extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_deal_id',
        'old_price',
        'new_price',
        'reason_id',
        'custom_reason',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejected_by',
        'rejected_reason',
        'status',
    ];

    protected static function newFactory(): ProjectDealPriceChangeFactory
    {
        return ProjectDealPriceChangeFactory::new();
    }

    protected $appends = [
        'real_reason',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectDealChangePriceStatus::class,
        ];
    }

    public function realReason(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->reason_id) {
                    return $this->reason->name;
                }

                return $this->custom_reason;
            }
        );
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(PriceChangeReason::class, 'reason_id');
    }

    public function projectDeal(): BelongsTo
    {
        return $this->belongsTo(ProjectDeal::class);
    }

    public function requesterBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
