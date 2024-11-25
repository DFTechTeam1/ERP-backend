<?php

namespace Modules\Production\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;
use Modules\Production\Database\Factories\ProjectTaskHoldFactory;

class ProjectTaskHold extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'project_task_id',
        'reason',
        'hold_at',
        'end_at',
        'hold_by'
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function holdByUser(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hold_by');
    }
}
