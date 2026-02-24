<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\GreatdayEmploymentStatusFactory;

class GreatdayEmploymentStatus extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code',
        'need_employment_date'
    ];

    // protected static function newFactory(): GreatdayEmploymentStatusFactory
    // {
    //     // return GreatdayEmploymentStatusFactory::new();
    // }
}
