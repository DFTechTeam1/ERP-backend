<?php

namespace Modules\Finance\Models;

use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Traits\ModelCreationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

// use Modules\Finance\Database\Factories\InvoiceRequestUpdateFactory;

class InvoiceRequestUpdate extends Model
{
    use HasFactory;

    protected static function booted()
    {
        // static::creating(function (InvoiceRequestUpdate $model) {
        //     $model->request_by = Auth::id();
        // });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'amount',
        'payment_date',
        'invoice_id',
        'status',
        'request_by'
    ];

    protected $casts = [
        'status' => InvoiceRequestUpdateStatus::class
    ];

    // protected static function newFactory(): InvoiceRequestUpdateFactory
    // {
    //     // return InvoiceRequestUpdateFactory::new();
    // }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
