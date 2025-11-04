<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskPicWorkstateFactory;

class InteractiveProjectTaskPicWorkstate extends Model
{
    use HasFactory;

    protected $table = 'intr_project_task_pic_workstates';

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

    // protected static function newFactory(): InteractiveProjectTaskPicWorkstateFactory
    // {
    //     // return InteractiveProjectTaskPicWorkstateFactory::new();
    // }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTask::class, 'task_id');
    }

    public function holdStates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InteractiveProjectTaskPicHoldstate::class, 'work_state_id');
    }
}
