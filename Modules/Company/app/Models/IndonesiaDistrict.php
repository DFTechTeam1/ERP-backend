<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use KodePandai\Indonesia\Models\District;
use Modules\Company\Database\Factories\DistrictFactory;

// use Modules\Company\Database\Factories\IndonesiaDistrictFactory;

class IndonesiaDistrict extends Model
{
    use HasFactory;

    // protected $primaryKey = 'code';

    protected $fillable = [
        'code', 'city_code', 'name', 'latitude', 'longitude',
    ];

    public $timestamps = false;

    protected $table = 'indonesia_districts';

    protected static function newFactory(): DistrictFactory
    {
        return DistrictFactory::new();
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(IndonesiaCity::class, 'city_code', 'code');
    }
}
