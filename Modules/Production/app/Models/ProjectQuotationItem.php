<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Production\Database\Factories\ProjectQuotationItemFactory;

class ProjectQuotationItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'quotation_id',
        'item_id'
    ];

    // protected static function newFactory(): ProjectQuotationItemFactory
    // {
    //     // return ProjectQuotationItemFactory::new();
    // }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\QuotationItem::class, 'item_id');
    }
}
