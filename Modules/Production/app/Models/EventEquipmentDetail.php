<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\EventEquipmentDetailFactory;

class EventEquipmentDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'event_equipment_id',
        'inventory_item_id',
        'custom_detail_id',
    ];

    // protected static function newFactory(): EventEquipmentDetailFactory
    // {
    //     // return EventEquipmentDetailFactory::new();
    // }
}
