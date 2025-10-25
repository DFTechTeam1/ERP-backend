<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Company\Database\Factories\ProjectClassFactory;

class ProjectClass extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'maximal_point',
        'color',
        'base_point',
        'point_2_team',
        'point_3_team',
        'point_4_team',
        'point_5_team',
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
