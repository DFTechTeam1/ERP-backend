<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\Database\Factories\ProjectDealRefundFactory;

// use Modules\Finance\Database\Factories\ProjectDealRefundFactory;

class ProjectDealRefund extends Model
{
    use HasFactory;

    // static booted method to fill created_by on creating event
    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_deal_id',
        'refund_amount',
        'refund_percentage',
        'refund_reason',
        'status',
        'refund_type',
        'created_by',
    ];

    protected static function newFactory(): ProjectDealRefundFactory
    {
        return ProjectDealRefundFactory::new();
    }

    public function casts(): array
    {
        return [
            'status' => \App\Enums\Finance\RefundStatus::class,
        ];
    }

    public function projectDeal(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\ProjectDeal::class, 'project_deal_id', 'id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function transaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'sourceable');
    }
}
