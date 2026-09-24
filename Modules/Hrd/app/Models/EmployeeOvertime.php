<?php

namespace Modules\Hrd\Models;

use App\Traits\ModelObserver;
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
        'overtime_hours',
        'remark',
        'project_id',
        'task_id',
        'task_name',
        'project_name',
        'employee_name',
        'position_name',
        'employee_number',
        'overtime_date'
    ];

    // protected static function newFactory(): EmployeeOvertimeFactory
    // {
    //     // return EmployeeOvertimeFactory::new();
    // }
    //
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
}
