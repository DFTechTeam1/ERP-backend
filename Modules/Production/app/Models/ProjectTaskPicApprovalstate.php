<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

// use Modules\Production\Database\Factories\ProjectTaskPicApprovalstateFactory;

class ProjectTaskPicApprovalstate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'pic_id',
        'task_id',
        'project_id',
        'work_state_id',
        'started_at',
        'approved_at',
    ];

    // protected static function newFactory(): ProjectTaskPicApprovalstateFactory
    // {
    //     // return ProjectTaskPicApprovalstateFactory::new();
    // }

    /**
     * Get the task that owns the approval state.
     * 
     * @return BelongsTo
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id', 'id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_id', 'id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    public function workState(): BelongsTo
    {
        return $this->belongsTo(ProjectTaskPicWorkstate::class, 'work_state_id', 'id');
    }
}
