<?php

namespace Modules\Production\Models;

use App\Enums\Production\WorkType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskPicLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_task_id',
        'employee_id',
        'work_type',
        'time_added',
    ];

    protected $appends = ['status_text'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }

    public function scopeAssigned(Builder $query): void
    {
        $query->where('work_type', WorkType::Assigned->value);
    }

    public function statusText(): Attribute
    {
        $out = '';
        if (isset($this->attributes['employee_id'])) {
            $employee = \Modules\Hrd\Models\Employee::select('nickname')->find($this->attributes['employee_id']);

            if (\App\Enums\Production\WorkType::OnProgress->value == $this->attributes['work_type']) {
                $out = __('global.employeeStartWorking', ['name' => $employee->nickname]);
            } elseif (\App\Enums\Production\WorkType::Assigned->value == $this->attributes['work_type']) {
                $out = __('global.employeeAssignedTask', ['name' => $employee->nickname]);
            } elseif (\App\Enums\Production\WorkType::CheckByPm->value == $this->attributes['work_type']) {
                $out = __('global.employeeCheckByPmTask', ['name' => $employee->nickname]);
            } elseif (\App\Enums\Production\WorkType::Revise->value == $this->attributes['work_type']) {
                $out = __('global.employeeReviseTask');
            } elseif (\App\Enums\Production\WorkType::Finish->value == $this->attributes['work_type']) {
                $out = __('global.employeeFinishTask', ['name' => $employee->nickname]);
            } elseif (\App\Enums\Production\WorkType::OnHold->value == $this->attributes['work_type']) {
                $out = __('global.onHoldTask', ['name' => $employee->nickname]);
            }
        }

        return Attribute::make(
            get: fn () => $out,
        );
    }

    public function timeAdded(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => date('d F Y H:i', strtotime($value)),
        );
    }
}
