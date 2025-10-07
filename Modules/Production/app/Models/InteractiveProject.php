<?php

namespace Modules\Production\Models;

use App\Enums\Production\ProjectStatus;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Production\Database\Factories\InteractiveProjectFactory;

// use Modules\Production\Database\Factories\InteractiveProjectFactory;

class InteractiveProject extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'client_portal',
        'parent_project',
        'project_date',
        'event_type',
        'venue',
        'marketing_id',
        'collaboration',
        'status',
        'canceled_by',
        'classification',
        'note',
        'led_area',
        'led_detail',
        'project_class_id',
        'created_by',
        'updated_by',
    ];

    protected function casts()
    {
        return [
            'status' => ProjectStatus::class,
        ];
    }

    protected static function newFactory(): InteractiveProjectFactory
    {
        return InteractiveProjectFactory::new();
    }

    public function ledDetail(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : null,
            set: fn ($value) => $value ? json_encode($value) : null
        );
    }

    public function parentProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'parent_project', 'id');
    }

    public function boards(): HasMany
    {
        return $this->hasMany(InteractiveProjectBoard::class, 'project_id', 'id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(InteractiveProjectTask::class, 'intr_project_id', 'id');
    }

    public function pics(): HasMany
    {
        return $this->hasMany(InteractiveProjectPic::class, 'intr_project_id', 'id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(InteractiveProjectReference::class, 'project_id', 'id');
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'canceled_by', 'id');
    }
}
