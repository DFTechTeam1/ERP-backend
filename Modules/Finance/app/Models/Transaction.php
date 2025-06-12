<?php

namespace Modules\Finance\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

// use Modules\Finance\Database\Factories\TransactionFactory;

class Transaction extends Model
{
    use HasFactory, ModelObserver;

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            $number = Transaction::select('id')
                ->count();
            // generate trx id
            $transaction->trx_id = 'TRX' . generateSequenceNumber(number: $number + 1, length: 6);
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

    // protected static function newFactory(): TransactionFactory
    // {
    //     // return TransactionFactory::new();
    // }

    public function attachments(): HasMany
    {
        return $this->hasMany(TransactionImage::class, 'transaction_id');
    }
}
