<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Company\Database\Factories\ProjectClassFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Production\Database\Factories\EventTypeFactory;

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

    protected static function newFactory(): EventTypeFactory
    {
        return EventTypeFactory::new();
    }

    public function project(): HasOne
    {
        return $this->hasOne(\Modules\Production\Models\Project::class, 'project_class_id');
    }
}
