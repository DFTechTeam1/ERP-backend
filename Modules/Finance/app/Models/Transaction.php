<?php

namespace Modules\Finance\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\Database\Factories\TransactionFactory;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;

// use Modules\Finance\Database\Factories\TransactionFactory;

class Transaction extends Model
{
    use HasFactory, ModelObserver;

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            $transaction->created_by = Auth::id();
        });

        static::created(function (Transaction $transaction) {
            $invoiceId = $transaction->invoice_id;

            \Modules\Finance\Models\Invoice::where('id', $invoiceId)
                ->update([
                    'status' => \App\Enums\Transaction\InvoiceStatus::Paid,
                    'paid_amount' => $transaction->payment_amount,
                ]);
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'project_deal_id',
        'customer_id',
        'payment_amount',
        'reference',
        'note',
        'invoice_id',
        'trx_id',
        'transaction_date',
        'transaction_type',
        'created_by',
        'sourceable_type',
        'sourceable_id',
        'debit_credit'
    ];

    protected $appends = [
        'transaction_date_raw',
    ];

    protected $casts = [
        'transaction_type' => \App\Enums\Transaction\TransactionType::class,
    ];

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TransactionImage::class, 'transaction_id');
    }

    public function projectDeal(): BelongsTo
    {
        return $this->belongsTo(ProjectDeal::class, 'project_deal_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function transactionDateRaw(): Attribute
    {
        $output = '-';

        if (isset($this->attributes['transaction_date'])) {
            $output = date('d F Y', strtotime($this->attributes['transaction_date']));
        }

        return Attribute::make(
            fn () => $output
        );
    }
}
