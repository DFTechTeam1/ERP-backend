<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTaskWorktime extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_task_id',
        'employee_id',
        'user_id',
        'project_id',
        'start_at',
        'end_at',
        'work_type',
    ];
}
