<?php

namespace Modules\Finance\Models;

use App\Enums\Finance\ExchangeRate\SourceRate;
use App\Models\User;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Finance\Database\Factories\ExchangeRateFactory;

class ExchangeRate extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'currency_id',
        'rate_date',
        'rate',
        'source',
        'created_by'
    ];

    // protected static function newFactory(): ExchangeRateFactory
    // {
    //     // return ExchangeRateFactory::new();
    // }

    protected $casts = [
        'source' => SourceRate::class
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
