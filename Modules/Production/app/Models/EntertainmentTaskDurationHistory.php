<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

class EntertainmentTaskDurationHistory extends Model
{
    use HasFactory;

    protected $table = 'entertainment_task_duration_histories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'task_id',
        'task_type',
        'pic_id',
        'employee_id',
        'task_full_duration',
        'task_holded_duration',
        'task_revised_duration',
        'task_actual_duration',
        'task_approval_duration',
        'total_task_holded',
        'total_task_revised',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task_full_duration' => 'integer',
            'task_holded_duration' => 'integer',
            'task_revised_duration' => 'integer',
            'task_actual_duration' => 'integer',
            'task_approval_duration' => 'integer',
            'total_task_holded' => 'integer',
            'total_task_revised' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTask::class, 'task_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
