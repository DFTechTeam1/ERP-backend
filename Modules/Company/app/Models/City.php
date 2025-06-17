<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Company\Database\Factories\CityFactory;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'country_id',
        'state_id',
        'name',
        'country_code',
    ];
}
