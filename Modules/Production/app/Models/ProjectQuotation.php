<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Production\Database\Factories\ProjectQuotationFactory;

class ProjectQuotation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_deal_id',
        'main_ballroom',
        'prefunction',
        'high_season_fee',
        'equipment_fee',
        'sub_total',
        'maximum_discount',
        'total',
        'maximum_markup_price',
        'fix_price',
        'quotation_id',
        'is_final',
        'description',
    ];

    // protected static function newFactory(): ProjectQuotationFactory
    // {
    //     // return ProjectQuotationFactory::new();
    // }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\ProjectDeal::class, 'project_deal_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(\Modules\Production\Models\ProjectQuotationItem::class, 'quotation_id');
    }

    public function scopeFinal(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where('is_final', 1);
    }
}
