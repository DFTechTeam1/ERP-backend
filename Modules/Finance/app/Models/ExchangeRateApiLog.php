<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\ExchangeRateApiLogFactory;

class ExchangeRateApiLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'url',
        'response',
        'is_success',
        'response_code',
        'actor_name'
    ];

    // protected static function newFactory(): ExchangeRateApiLogFactory
    // {
    //     // return ExchangeRateApiLogFactory::new();
    // }

    protected $casts = [
        'is_success' => 'boolean'
    ];
}
