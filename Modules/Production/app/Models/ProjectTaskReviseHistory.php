<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\ProjectTaskReviseHistoryFactory;

class ProjectTaskReviseHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_task_id',
        'project_id',
        'selected_user',
        'reason',
        'file',
        'revise_by',
    ];
}
