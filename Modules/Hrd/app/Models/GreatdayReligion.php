<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\GreatdayReligionFactory;

class GreatdayReligion extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code'
    ];

    // protected static function newFactory(): GreatdayReligionFactory
    // {
    //     // return GreatdayReligionFactory::new();
    // }
}
