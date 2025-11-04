<?php

namespace Modules\Production\Models;

use App\Enums\Interactive\InteractiveRequestStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

// use Modules\Production\Database\Factories\InteractiveRequestFactory;

class InteractiveRequest extends Model
{
    use HasFactory;

    // observer for requester_id
    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->requester_id = Auth::id();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_deal_id',
        'requester_id',
        'status',
        'interactive_detail',
        'interactive_area',
        'interactive_note',
        'interactive_fee',
        'fix_price',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
    ];

    // protected static function newFactory(): InteractiveRequestFactory
    // {
    //     // return InteractiveRequestFactory::new();
    // }

    protected function casts()
    {
        return [
            'status' => InteractiveRequestStatus::class,
        ];
    }

    public function interactiveDetail(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : null,
            set: fn ($value) => $value ? json_encode($value) : null
        );
    }

    public function projectDeal(): BelongsTo
    {
        return $this->belongsTo(ProjectDeal::class, 'project_deal_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requester_id');
    }
}
