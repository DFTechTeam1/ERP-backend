<?php

namespace Modules\Company\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    public $timestamps = false;

    protected static function newFactory(): CityFactory
    {
        return CityFactory::new();
    }

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
