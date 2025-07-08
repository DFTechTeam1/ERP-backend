<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'task_duration',
        'pm_approval_duration',
        'task_type',
        'is_task_revised',
        'is_task_deadline_updated',
        'created_at'
    ];

    // protected static function newFactory(): ProjectTaskDurationHistoryFactory
    // {
    //     // return ProjectTaskDurationHistoryFactory::new();
    // }
}
