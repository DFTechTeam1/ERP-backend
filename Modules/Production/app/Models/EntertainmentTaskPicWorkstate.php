<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Hrd\Models\Employee;

class EntertainmentTaskPicWorkstate extends Model
{
    use HasFactory;

    protected $table = 'entertainment_task_pic_workstates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'started_at',
        'first_finish_at',
        'complete_at',
        'task_id',
        'employee_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'first_finish_at' => 'datetime',
            'complete_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTask::class, 'task_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approvalStates(): HasMany
    {
        return $this->hasMany(EntertainmentTaskPicApprovalstate::class, 'work_state_id');
    }

    public function holdStates(): HasMany
    {
        return $this->hasMany(EntertainmentTaskPicHoldstate::class, 'work_state_id');
    }

    public function reviseStates(): HasMany
    {
        return $this->hasMany(EntertainmentTaskPicRevisestate::class, 'work_state_id');
    }
}
