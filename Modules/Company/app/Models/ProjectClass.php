<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Company\Database\Factories\ProjectClassFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectClass extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'maximal_point',
        'color'
    ];

    public function project(): HasOne
    {
        return $this->hasOne(\Modules\Production\Models\Project::class, 'project_class_id');
    }
}
