<?php

namespace Modules\Company\Models;

use Database\Factories\CountryFactory as FactoriesCountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'iso3',
        'iso2',
        'phone_code',
        'currency',
    ];

    protected static function newFactory(): FactoriesCountryFactory
    {
        return FactoriesCountryFactory::new();
    }

    public function states(): HasMany
    {
        return $this->hasMany(State::class, 'country_id');
    }
}
