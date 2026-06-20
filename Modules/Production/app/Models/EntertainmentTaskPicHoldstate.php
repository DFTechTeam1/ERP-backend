<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

class EntertainmentTaskPicHoldstate extends Model
{
    use HasFactory;

    protected $table = 'entertainment_task_pic_holdstates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reason',
        'holded_at',
        'unholded_at',
        'task_id',
        'employee_id',
        'work_state_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'holded_at' => 'datetime',
            'unholded_at' => 'datetime',
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

    public function workState(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskPicWorkstate::class, 'work_state_id');
    }
}
