<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\GreatdayJobGradeFactory;

class GreatdayJobGrade extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code'
    ];

    // protected static function newFactory(): GreatdayJobGradeFactory
    // {
    //     // return GreatdayJobGradeFactory::new();
    // }
}
