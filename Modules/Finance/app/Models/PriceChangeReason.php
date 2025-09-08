<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Finance\Database\Factories\PriceChangeReasonFactory;

// use Modules\Finance\Database\Factories\PriceChangeReasonFactory;

class PriceChangeReason extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    protected static function newFactory(): PriceChangeReasonFactory
    {
        return PriceChangeReasonFactory::new();
    }
}
