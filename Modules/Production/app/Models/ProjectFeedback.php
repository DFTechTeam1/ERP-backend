<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Production\Database\Factories\ProjectFeedbackFactory;

class ProjectFeedback extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'pic_id',
        'feedback',
        'points',
        'submitted_at',
        'submitted_by',
    ];

    // protected static function newFactory(): ProjectFeedbackFactory
    // {
    //     // return ProjectFeedbackFactory::new();
    // }

    public function points(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => json_decode($value, true),
            set: fn ($value) => json_encode($value),
        );
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'pic_id');
    }
}
