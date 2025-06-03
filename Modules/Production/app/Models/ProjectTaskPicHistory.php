<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTaskPicHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'project_task_id',
        'employee_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\Project::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\ProjectTask::class, 'project_task_id');
    }

    public function taskLog(): HasMany
    {
        return $this->hasMany(\Modules\Production\Models\ProjectTaskPicLog::class, 'project_task_id', 'project_task_id');
    }
}
