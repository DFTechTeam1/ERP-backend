<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\Database\Factories\ProjectClassFactory;

class ProjectClass extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'maximal_point',
        'color',
        'base_point', // Legacy
        'point_2_team', // Legacy
        'point_3_team', // Legacy
        'point_4_team', // Legacy
        'point_5_team', // Legacy
        'reward',
        'is_active'
    ];

    protected static function newFactory(): ProjectClassFactory
    {
        return ProjectClassFactory::new();
    }

    public function project(): HasOne
    {
        return $this->hasOne(\Modules\Production\Models\Project::class, 'project_class_id');
    }
}
