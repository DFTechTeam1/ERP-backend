<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Inventory\Database\Factories\ProjectEquipmentDetailFactory;

class ProjectEquipmentDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_equipment_id',
        'inventory_item_id',
        'custom_inventory_id',
        'qty',
        'equipment_type',
        'created_by'
    ];

    // protected static function newFactory(): ProjectEquipmentDetailFactory
    // {
    //     // return ProjectEquipmentDetailFactory::new();
    // }
}
