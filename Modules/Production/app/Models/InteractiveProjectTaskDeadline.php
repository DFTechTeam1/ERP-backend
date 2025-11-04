<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskDeadlineFactory;

class InteractiveProjectTaskDeadline extends Model
{
    use HasFactory;

    protected $table = 'intr_project_task_deadlines';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'deadline',
        'start_time',
        'actual_end_time',
        'employee_id',
    ];

    // protected static function newFactory(): InteractiveProjectTaskDeadlineFactory
    // {
    //     // return InteractiveProjectTaskDeadlineFactory::new();
    // }
}
