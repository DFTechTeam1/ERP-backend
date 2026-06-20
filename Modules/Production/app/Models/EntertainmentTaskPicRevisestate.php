<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

class EntertainmentTaskPicRevisestate extends Model
{
    use HasFactory;

    protected $table = 'entertainment_task_pic_revisestates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_id',
        'work_state_id',
        'employee_id',
        'assign_at',
        'start_at',
        'finish_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assign_at' => 'datetime',
            'start_at' => 'datetime',
            'finish_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTask::class, 'task_id');
    }

    public function workState(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskPicWorkstate::class, 'work_state_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
