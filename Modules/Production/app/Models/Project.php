<?php

namespace Modules\Production\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Production\Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Production\Observers\NasFolderObserver;

#[ObservedBy([NasFolderObserver::class])]
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
        'showreels',
        'country_id',
        'state_id',
        'city_id',
        'city_name',
        'project_class_id',
        'showreels_status',
        'longitude',
        'latitude',
    ];

    protected $appends = ['status_text', 'status_color', 'event_type_text', 'event_class_text', 'event_class_color', 'showreels_path'];

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    /**
     * Get all of the personInCharges for the Project
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function personInCharges(): HasMany
    {
        return $this->hasMany(ProjectPersonInCharge::class, 'project_id');
    }

    public function vjs(): HasMany
    {
        return $this->hasMany(\Modules\Production\Models\ProjectVj::class, 'project_id');
    }

    public function vj(): HasOne
    {
        return $this->hasOne(\Modules\Production\Models\ProjectVj::class, 'project_id');
    }

    public function projectClass(): BelongsTo
    {
        return $this->belongsTo(\Modules\Company\Models\ProjectClass::class, 'project_class_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(\Modules\Company\Models\Country::class, 'country_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(\Modules\Company\Models\State::class, 'state_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(\Modules\Company\Models\City::class, 'city_id');
    }

    public function marketings(): HasMany
    {
        return $this->hasMany(ProjectMarketing::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'project_id');
    }

    public function teamTransfer(): HasMany
    {
        return $this->hasMany(TransferTeamMember::class, 'project_id');
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

    public function equipments(): HasMany
    {
        return $this->hasMany(ProjectEquipment::class, 'project_id');
    }

    public function statusText(): Attribute
    {
        $output = __('global.undetermined');

        if ($this->status) {
            $statuses = \App\Enums\Production\ProjectStatus::cases();
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
        $output = 'grey-lighten-1';

        if ($this->status) {
            $statuses = \App\Enums\Production\ProjectStatus::cases();
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

    public function showreelsPath(): Attribute
    {
        $output = '';

        if (isset($this->attributes['showreels'])) {
            $output = asset('storage/projects/' . $this->attributes['id'] . '/showreels/' . $this->attributes['showreels']);
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }

    public function eventTypeText(): Attribute
    {
        $output = '-';

        if ($this->event_type) {
            $statuses = \App\Enums\Production\EventType::cases();
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
            $statuses = \App\Enums\Production\Classification::cases();
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
            $statuses = \App\Enums\Production\Classification::cases();
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
