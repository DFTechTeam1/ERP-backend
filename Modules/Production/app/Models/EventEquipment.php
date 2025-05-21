<?php

namespace Modules\Production\Models;

use App\Enums\Inventory\EventEquipmentStatus;
use App\Enums\Inventory\EventEquipmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// use Modules\Production\Database\Factories\EventEquipmentFactory;

class EventEquipment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'type',
        'requested_by',
        'status'
    ];

    protected $table = 'event_equipments';

    protected $casts = [
        'type' => EventEquipmentType::class,
        'status' => EventEquipmentStatus::class
    ];

    // protected static function newFactory(): EventEquipmentFactory
    // {
    //     // return EventEquipmentFactory::new();
    // }

    public function details(): HasMany
    {
        return $this->hasMany(EventEquipmentDetail::class, 'event_equipment_id');
    }
}
