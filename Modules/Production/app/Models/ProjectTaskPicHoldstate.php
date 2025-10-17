<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Production\Database\Factories\ProjectTaskPicHoldstateFactory;

class ProjectTaskPicHoldstate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'holded_at',
        'reason',
        'unholded_at',
        'task_id',
        'employee_id',
        'work_state_id',
    ];

    // protected static function newFactory(): ProjectTaskPicHoldstateFactory
    // {
    //     // return ProjectTaskPicHoldstateFactory::new();
    // }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }

    public function workState(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\ProjectTaskPicWorkstate::class, 'work_state_id');
    }
}
