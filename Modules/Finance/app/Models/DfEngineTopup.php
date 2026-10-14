<?php

namespace Modules\Finance\Models;

use App\Models\User;
use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Finance\Database\Factories\DfEngineTopupFactory;

class DfEngineTopup extends Model
{
    use HasFactory, ModelObserver, ModelCreationObserver;

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
    //
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
