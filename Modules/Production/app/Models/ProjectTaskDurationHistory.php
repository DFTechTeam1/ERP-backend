<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\ProjectTaskDurationHistoryFactory;

// use Modules\Production\Database\Factories\ProjectTaskDurationHistoryFactory;

class ProjectTaskDurationHistory extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'task_id',
        'pic_id',
        'employee_id',
        'task_type',
        'task_full_duration',
        'task_holded_duration',
        'task_revised_duration',
        'task_actual_duration',
        'task_approval_duration',
        'total_task_holded',
        'total_task_revised',
    ];

    protected static function newFactory(): ProjectTaskDurationHistoryFactory
    {
        return ProjectTaskDurationHistoryFactory::new();
    }
}
