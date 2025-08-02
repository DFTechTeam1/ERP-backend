<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\Database\Factories\CityFactory;

// use Modules\Company\Database\Factories\IndonesiaCityFactory;

class IndonesiaCity extends Model
{
    use HasFactory;

    // protected $primaryKey = 'code';

    public $timestamps = false;

    protected $fillable = [
        'code', 'province_code', 'name', 'latitude', 'longitude',
    ];

    protected $table = 'indonesia_cities';

    protected static function newFactory(): CityFactory
    {
        return CityFactory::new();
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(IndonesiaDistrict::class, 'city_code', 'code');
    }
}
