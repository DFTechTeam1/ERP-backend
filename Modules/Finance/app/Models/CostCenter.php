<?php

namespace Modules\Finance\Models;

use App\Enums\Finance\CostCenter\CostCenterType;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Finance\Database\Factories\CostCenterFactory;

class CostCenter extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'code',
        'name',
        'parent_id',
        'is_active',
        'type'
    ];

    // protected static function newFactory(): CostCenterFactory
    // {
    //     // return CostCenterFactory::new();
    // }
    //
    protected $casts = [
        'type' => CostCenterType::class
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }
}
