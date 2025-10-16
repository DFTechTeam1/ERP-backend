<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

// use Modules\Production\Database\Factories\ProjectTaskPicWorkstateFactory;

class ProjectTaskPicWorkstate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'started_at',
        'first_finish_at',
        'complete_at',
        'task_id',
        'employee_id',
    ];

    // protected static function newFactory(): ProjectTaskPicWorkstateFactory
    // {
    //     // return ProjectTaskPicWorkstateFactory::new();
    // }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id', 'id');
    }
}
