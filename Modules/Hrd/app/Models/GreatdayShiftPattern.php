<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\GreatdayShiftPatternFactory;

class GreatdayShiftPattern extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code',
        'total_working_hour_per_day',
        'total_day_off_per_week',
        'note',
    ];

    // protected static function newFactory(): GreatdayShiftPatternFactory
    // {
    //     // return GreatdayShiftPatternFactory::new();
    // }
}
