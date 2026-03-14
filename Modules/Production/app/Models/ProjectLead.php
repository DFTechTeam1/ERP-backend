<?php

namespace Modules\Production\Models;

use App\Enums\Production\EventType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Company\Models\City;
use Modules\Hrd\Models\Employee;

// use Modules\Production\Database\Factories\ProjectLeadFactory;

class ProjectLead extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'customer_phone',
        'project_date',
        'event_type',
        'venue',
        'city_id',
        'pic_id',
        'collaboration',
        'note',
        'total_led',
        'project_class_id',
        'created_by',
        'updated_by',
        'is_final',
    ];

    // protected static function newFactory(): ProjectLeadFactory
    // {
    //     // return ProjectLeadFactory::new();
    // }

    protected function casts()
    {
        return [
            'event_type' => EventType::class
        ];
    }

    public function picId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => $value ? json_encode($value) : null
        );
    }

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function updatedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by');
    }
}
