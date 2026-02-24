<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\GreatdayWorkLocationFactory;

class GreatdayWorkLocation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'max_radius'
    ];

    // protected static function newFactory(): GreatdayWorkLocationFactory
    // {
    //     // return GreatdayWorkLocationFactory::new();
    // }
}
