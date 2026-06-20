<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

class EntertainmentTaskPicApprovalstate extends Model
{
    use HasFactory;

    protected $table = 'entertainment_task_pic_approvalstates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pic_id',
        'task_id',
        'project_id',
        'work_state_id',
        'started_at',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTask::class, 'task_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workState(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskPicWorkstate::class, 'work_state_id');
    }
}
