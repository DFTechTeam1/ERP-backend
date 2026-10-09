<?php

namespace Modules\Finance\Models;

use App\Enums\Finance\ExchangeRate\SourceRate;
use App\Models\User;
use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Finance\Database\Factories\ExchangeRateFactory;

class ExchangeRate extends Model
{
    use HasFactory, ModelObserver, ModelCreationObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'currency_id',
        'rate_date',
        'rate',
        'source',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => SourceRate::class,
        ];
    }

    // protected static function newFactory(): ExchangeRateFactory
    // {
    //     // return ExchangeRateFactory::new();
    // }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
