<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\GreatdayCompanyFactory;

class GreatdayCompany extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code',
        'company_id',
        'nickname',
        'is_base_office',
        'address',
        'address2',
    ];

    // protected static function newFactory(): GreatdayCompanyFactory
    // {
    //     // return GreatdayCompanyFactory::new();
    // }
}
