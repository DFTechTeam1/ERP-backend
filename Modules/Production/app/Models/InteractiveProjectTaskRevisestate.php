<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskRevisestateFactory;

class InteractiveProjectTaskRevisestate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'work_state_id',
        'employee_id',
        'assign_at',
        'start_at',
        'finish_at',
    ];

    // protected static function newFactory(): InteractiveProjectTaskRevisestateFactory
    // {
    //     // return InteractiveProjectTaskRevisestateFactory::new();
    // }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTask::class, 'task_id');
    }

    public function workState(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTaskPicWorkstate::class, 'work_state_id');
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }
}
