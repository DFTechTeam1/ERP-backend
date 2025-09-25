<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskPicHoldstateFactory;

class InteractiveProjectTaskPicHoldstate extends Model
{
    use HasFactory;

    protected $table = 'intr_project_task_pic_holdstates';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'holded_at',
        'unholded_at',
        'task_id',
        'employee_id',
        'work_state_id',
    ];

    // protected static function newFactory(): InteractiveProjectTaskPicHoldstateFactory
    // {
    //     // return InteractiveProjectTaskPicHoldstateFactory::new();
    // }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTask::class, 'task_id');
    }
}
