<?php

namespace Modules\Finance\Models;

use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Models\User;
use App\Traits\ModelCreationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\Database\Factories\InvoiceRequestUpdateFactory;

// use Modules\Finance\Database\Factories\InvoiceRequestUpdateFactory;

class InvoiceRequestUpdate extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::creating(function (InvoiceRequestUpdate $model) {
            $model->request_by = Auth::id();
        });
    }

    protected static function newFactory(): InvoiceRequestUpdateFactory
    {
        return InvoiceRequestUpdateFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'amount',
        'payment_date',
        'invoice_id',
        'status',
        'request_by',
        'approved_by',
        'rejected_by',
        'reason',
        'approved_at',
        'rejected_at'
    ];
    
    protected function casts()
    {
        return [
            'status' => InvoiceRequestUpdateStatus::class
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'request_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', InvoiceRequestUpdateStatus::Approved->value);
    }
}
