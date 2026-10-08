<?php

namespace Modules\Finance\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Finance\Database\Factories\CurrencyFactory;

class Currency extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'symbol',
        'code', // ISO 4217
        'is_base',
        'is_active'
    ];

    // protected static function newFactory(): CurrencyFactory
    // {
    //     // return CurrencyFactory::new();
    // }

    public function rates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency_id');
    }

    public function latestRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency_id')
            ->latest()
            ->limit(2);
    }
}
