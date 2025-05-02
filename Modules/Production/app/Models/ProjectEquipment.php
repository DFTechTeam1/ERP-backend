<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\ProjectEquipmentFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\ProjectEquipmentDetail;

class ProjectEquipment extends Model
{
    use HasFactory, ModelObserver, ModelCreationObserver;

    protected $table = 'project_equipments';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'total_equipment',
        'status',
        'type'
    ];

    protected $appends = ['status_text', 'status_color'];

    // public function inventory(): BelongsTo
    // {
    //     return $this->belongsTo(\Modules\Inventory\Models\Inventory::class, 'inventory_id');
    // }

    // public function userCreated(): BelongsTo
    // {
    //     return $this->belongsTo(\App\Models\User::class, 'created_by');
    // }

    public function details(): HasMany
    {
        return $this->hasMany(ProjectEquipmentDetail::class, 'project_equipment_id');
    }

    public function statusText(): Attribute
    {
        $out = '-';
        if ($this->status) {
            $statuses = \App\Enums\Production\RequestEquipmentStatus::cases();
            foreach ($statuses as $status) {
                if ($status->value == $this->status) {
                    $out = $status->label();
                }
            }
        }
        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function statusColor(): Attribute
    {
        $out = '-';
        if ($this->status) {
            $statuses = \App\Enums\Production\RequestEquipmentStatus::cases();
            foreach ($statuses as $status) {
                if ($status->value == $this->status) {
                    $out = $status->color();
                }
            }
        }
        return Attribute::make(
            get: fn() => $out,
        );
    }
}
