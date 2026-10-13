<?php

namespace Modules\Finance\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\DfEngineTopupFactory;

class DfEngineTopup extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'created_by',
        'amount_usd',
        'exchange_rate',
        'note',
        'reference',
        'topup_date',
        'proof_of_payment'
    ];

    // protected static function newFactory(): DfEngineTopupFactory
    // {
    //     // return DfEngineTopupFactory::new();
    // }
}
