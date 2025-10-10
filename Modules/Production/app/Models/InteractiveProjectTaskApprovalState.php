<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

// use Modules\Production\Database\Factories\InteractiveProjectTaskApprovalStateFactory;

class InteractiveProjectTaskApprovalState extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string|null
     */
    protected $table = 'intr_project_task_approval_states';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'pic_id',
        'task_id',
        'project_id',
        'started_at',
        'approved_at',
        'work_state_id',
    ];

    // protected static function newFactory(): InteractiveProjectTaskApprovalStateFactory
    // {
    //     // return InteractiveProjectTaskApprovalStateFactory::new();
    // }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTask::class, 'task_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(InteractiveProject::class, 'project_id');
    }
}
