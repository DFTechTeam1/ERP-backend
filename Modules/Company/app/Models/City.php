<?php

namespace Modules\Company\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function projectDeals(): HasMany
    {
        return $this->hasMany(\Modules\Production\Models\ProjectDeal::class, 'city_id');
    }

    public function lastProjectDeal(): HasOne
    {
        return $this->hasOne(\Modules\Production\Models\ProjectDeal::class, 'city_id')->latestOfMany();
    }
}
