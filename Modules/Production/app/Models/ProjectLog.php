<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Production\Database\Factories\ProjectLogFactory;

class ProjectLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'message'
    ];

    // protected static function newFactory(): ProjectLogFactory
    // {
    //     // return ProjectLogFactory::new();
    // }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\Project::class, 'project_id');
    }
}
