<?php

namespace Modules\Finance\Models;

use App\Services\GeneralService;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;

// use Modules\Finance\Database\Factories\TransactionFactory;

class Transaction extends Model
{
    use HasFactory, ModelObserver;

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            $generalService = new GeneralService();
            $number = Transaction::select('id')
                ->count();
            // generate trx id
            $transaction->trx_id = $generalService->generateInvoiceNumber();
            $transaction->created_by = Auth::id();
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
        'trx_id',
        'transaction_date',
        'created_by'
    ];

    protected $appends = [
        'transaction_date_raw'
    ];

    // protected static function newFactory(): TransactionFactory
    // {
    //     // return TransactionFactory::new();
    // }

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
            fn() => $output
        );
    }
}
