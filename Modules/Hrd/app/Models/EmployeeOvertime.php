<?php

namespace Modules\Hrd\Models;

use App\Enums\Employee\OvertimeStatus;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;

// use Modules\Hrd\Database\Factories\EmployeeOvertimeFactory;

class EmployeeOvertime extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'employee_id',
        'master_overtime_hours',
        'overtime_hours',
        'remark',
        'project_id',
        'task_id',
        'task_name',
        'project_name',
        'employee_name',
        'position_name',
        'employee_number',
        'overtime_date',
        'status'
    ];

    // protected static function newFactory(): EmployeeOvertimeFactory
    // {
    //     // return EmployeeOvertimeFactory::new();
    // }
    //

    protected $casts = [
        'status' => OvertimeStatus::class
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function getEmployeeName(): string
    {
        if (! $this->relationLoaded('employee')) {
            $this->with('employee:id,name');
        }

        return $this?->employee?->name ?? $this->attributes['employee_name'];
    }

    public function taskId(): Attribute
    {
        return Attribute::make(
            set: fn($val) => $val ? json_encode($val) : null,
            get: fn($val) => $val ? json_decode($val, true) : []
        );
    }

    public function taskName(): Attribute
    {
        return Attribute::make(
            set: fn($val) => $val ? json_encode($val) : null,
            get: fn($val) => $val ? json_decode($val, true) : []
        );
    }
}
