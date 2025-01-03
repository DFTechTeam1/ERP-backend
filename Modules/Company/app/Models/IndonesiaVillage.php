<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\Database\Factories\VillageFactory;

// use Modules\Company\Database\Factories\IndonesiaVillageFactory;

class IndonesiaVillage extends Model
{
    use HasFactory;

    // protected $primaryKey = 'code';

    protected $fillable = [
        'code', 'district_code', 'name', 'latitude', 'longitude', 'postal_code',
    ];

    public $timestamps = false;

    protected $table = 'indonesia_villages';

    protected static function newFactory(): VillageFactory
    {
        return VillageFactory::new();
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(IndonesiaDistrict::class, 'district_code', 'code');
    }
}
