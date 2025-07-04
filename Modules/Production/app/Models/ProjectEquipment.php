<?php

namespace Modules\Production\Models;

use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEquipment extends Model
{
    use HasFactory, ModelCreationObserver, ModelObserver;

    protected $table = 'project_equipment';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'inventory_id',
        'qty',
        'status',
        'project_date',
        'created_by',
        'updated_by',
        'uid',
        'is_checked_pic',
        'inventory_code',
        'is_good_condition',
        'detail_condition',
    ];

    protected $appends = ['status_text', 'status_color'];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(\Modules\Inventory\Models\Inventory::class, 'inventory_id');
    }

    public function userCreated(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
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
            get: fn () => $out,
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
            get: fn () => $out,
        );
    }
}
