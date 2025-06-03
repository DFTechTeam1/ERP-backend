<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// use Modules\Production\Database\Factories\ProjectDealFactory;

class ProjectDeal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'project_date',
        'customer_id',
        'event_type',
        'venue',
        'collaboration',
        'note',
        'led_area',
        'led_detail',
        'country_id',
        'state_id',
        'city_id',
        'project_class_id',
        'longitude',
        'latitude',
        'equipment_type',
        'is_high_season',
        'status',
    ];

    // protected static function newFactory(): ProjectDealFactory
    // {
    //     // return ProjectDealFactory::new();
    // }

    protected $casts = [
        'event_type' => \App\Enums\Production\EventType::class,
        'equipment_type' => \App\Enums\Production\EquipmentType::class,
    ];

    public function ledDetail(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : null,
            set: fn ($value) => $value ? json_encode($value) : null
        );
    }

    public function marketings(): HasMany
    {
        return $this->hasMany(ProjectDealMarketing::class, 'project_deal_id')   
            ->select(
                'id',
                \Illuminate\Support\Facades\DB::raw('project_deal_id as marketing_project_deal_id'),
                'employee_id'
            );
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(ProjectQuotation::class, 'project_deal_id');
    }

    public function finalQuotation(): HasOne
    {
        return $this->hasOne(ProjectQuotation::class, 'project_deal_id')
            ->final();
    }

    public function latestQuotation(): HasOne
    {
        return $this->hasOne(ProjectQuotation::class, 'project_deal_id')
            ->latestOfMany();
    }

    public function city(): BelongsTo
    {
        return $this->BelongsTo(\Modules\Company\Models\City::class, 'city_id');
    }
}
