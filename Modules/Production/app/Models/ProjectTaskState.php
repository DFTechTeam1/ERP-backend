<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProjectTaskStateFactory;

class ProjectTaskState extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'project_task_id',
        'project_board_id',
        'project_id'
    ];

    // protected static function newFactory(): ProjectTaskStateFactory
    // {
    //     // return ProjectTaskStateFactory::new();
    // }
}
