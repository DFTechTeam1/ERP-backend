<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\Database\Factories\ProvinceFactory;

// use Modules\Company\Database\Factories\ProvinceFactory;

class Province extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code', 'name', 'latitude', 'longitude',
    ];

    protected $table = 'indonesia_provinces';

    protected static function newFactory(): ProvinceFactory
    {
        return ProvinceFactory::new();
    }

    public function cities(): HasMany
    {
        return $this->hasMany(IndonesiaCity::class, 'province_code', 'code');
    }
}
