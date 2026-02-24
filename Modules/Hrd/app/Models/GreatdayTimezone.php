<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\GreatdayTimezoneFactory;

class GreatdayTimezone extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'timezone_id',
        'gmt_ref_hour',
        'gmt_ref_minute',
        'gmt_plus_min'
    ];

    // protected static function newFactory(): GreatdayTimezoneFactory
    // {
    //     // return GreatdayTimezoneFactory::new();
    // }
}
