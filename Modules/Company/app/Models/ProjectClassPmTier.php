<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Company\Database\Factories\ProjectClassPmTierFactory;

class ProjectClassPmTier extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_class_id',
        'pm_count',
        'pm_reward',
        'production_reward',
    ];

    // protected static function newFactory(): ProjectClassPmTierFactory
    // {
    //     // return ProjectClassPmTierFactory::new();
    // }

    public function projectClass(): BelongsTo
    {
        return $this->belongsTo(ProjectClass::class, 'project_class_id');
    }
}
