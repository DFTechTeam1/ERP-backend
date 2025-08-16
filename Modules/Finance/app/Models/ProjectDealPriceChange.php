<?php

namespace Modules\Finance\Models;

use App\Enums\Production\ProjectDealChangePriceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Database\Factories\ProjectDealPriceChangeFactory;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealChange;

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
        'reason',
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

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectDealChangePriceStatus::class,
        ];
    }

    public function projectDeal(): BelongsTo
    {
        return $this->belongsTo(ProjectDeal::class);
    }

    public function requesterBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }
}
