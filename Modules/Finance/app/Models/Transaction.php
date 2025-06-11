<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\TransactionFactory;

class Transaction extends Model
{
    use HasFactory;

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
}
