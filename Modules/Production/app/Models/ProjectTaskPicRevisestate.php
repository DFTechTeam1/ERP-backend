<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

// use Modules\Production\Database\Factories\ProjectTaskPicRevisestateFactory;

class ProjectTaskPicRevisestate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'work_state_id',
        'employee_id',
        'assign_at',
        'start_at',
        'finish_at',
    ];

    // protected static function newFactory(): ProjectTaskPicRevisestateFactory
    // {
    //     // return ProjectTaskPicRevisestateFactory::new();
    // }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id', 'id');
    }

    public function workState(): BelongsTo
    {
        return $this->belongsTo(ProjectTaskPicWorkstate::class, 'work_state_id', 'id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
