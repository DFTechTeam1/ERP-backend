<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\PriceLedGuideFactory;

class PriceLedGuide extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'country_id',
        'state_id',
        'city_id',
        'price'
    ];

    // protected static function newFactory(): PriceLedGuideFactory
    // {
    //     // return PriceLedGuideFactory::new();
    // }
}
