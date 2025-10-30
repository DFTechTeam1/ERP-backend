<?php

namespace Modules\Company\Models;

use Database\Factories\CountryFactory as FactoriesCountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function projectDeals(): HasMany
    {
        return $this->hasMany(\Modules\Production\Models\ProjectDeal::class, 'country_id');
    }

    public function lastProjectDeal(): HasOne
    {
        return $this->hasOne(\Modules\Production\Models\ProjectDeal::class, 'country_id')->latestOfMany();
    }
}
