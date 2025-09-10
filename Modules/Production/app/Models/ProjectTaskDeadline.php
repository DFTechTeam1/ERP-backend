<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Production\Database\Factories\ProjectTaskDeadlineFactory;

// use Modules\Production\Database\Factories\ProjectTaskDeadlineFactory;

class ProjectTaskDeadline extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_task_id',
        'employee_id',
        'deadline',
        'actual_finish_time',
        'is_first_deadline',
        'due_reason',
        'custom_reason',
        'updated_by',
    ];

    protected static function newFactory(): ProjectTaskDeadlineFactory
    {
        return ProjectTaskDeadlineFactory::new();
    }
}
