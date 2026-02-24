<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\GreatdayCostCenterFactory;

class GreatdayCostCenter extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name_en',
        'name_id',
        'code'
    ];

    // protected static function newFactory(): GreatdayCostCenterFactory
    // {
    //     // return GreatdayCostCenterFactory::new();
    // }
}
