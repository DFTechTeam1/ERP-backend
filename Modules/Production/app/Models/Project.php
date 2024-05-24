<?php

namespace Modules\Production\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Production\Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Project extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'uid',
        'client_portal',
        'project_date',
        'event_type',
        'venue',
        'marketing_id',
        'collaboration',
        'note',
        'status',
        'classification',
        'led_area',
        'led_detail',
        'created_by',
        'updated_by',
    ];

    protected $appends = ['status_text', 'status_color', 'event_type_text', 'event_class_text', 'event_class_color'];

    /**
     * Get all of the personInCharges for the Project
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function personInCharges(): HasMany
    {
        return $this->hasMany(ProjectPersonInCharge::class, 'project_id');
    }

    /**
     * Get all of the boards for the Project
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function boards(): HasMany
    {
        return $this->hasMany(ProjectBoard::class, 'project_id');
    }

    public function marketing(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'marketing_id');
    }

    /**
     * Get all of the references for the Project
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function references(): HasMany
    {
        return $this->hasMany(ProjectReference::class, 'project_id');
    }

    public function statusText(): Attribute
    {
        $output = '-';

        if ($this->status) {
            $statuses = \App\enums\Production\ProjectStatus::cases();
            foreach ($statuses as $status) {
                if ($status->value == $this->status) {
                    $output = $status->label();
                }
            }
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }

    public function statusColor(): Attribute
    {
        $output = '-';

        if ($this->status) {
            $statuses = \App\enums\Production\ProjectStatus::cases();
            foreach ($statuses as $status) {
                if ($status->value == $this->status) {
                    $output = $status->color();
                }
            }
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }

    public function eventTypeText(): Attribute
    {
        $output = '-';

        if ($this->event_type) {
            $statuses = \App\enums\Production\EventType::cases();
            foreach ($statuses as $status) {
                if ($status->value == $this->event_type) {
                    $output = $status->label();
                }
            }
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }

    public function eventClassText(): Attribute
    {
        $output = '-';

        if ($this->classification) {
            $statuses = \App\enums\Production\Classification::cases();
            foreach ($statuses as $status) {
                if ($status->value == $this->classification) {
                    $output = $status->label();
                }
            }
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }

    public function eventClassColor(): Attribute
    {
        $output = '-';

        if ($this->classification) {
            $statuses = \App\enums\Production\Classification::cases();
            foreach ($statuses as $status) {
                if ($status->value == $this->classification) {
                    $output = $status->color();
                }
            }
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }

    // protected static function newFactory(): ProjectFactory
    // {
    //     //return ProjectFactory::new();
    // }
}
