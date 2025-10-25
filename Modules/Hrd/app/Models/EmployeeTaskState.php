<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Models\ProjectTask;

// use Modules\Hrd\Database\Factories\EmployeeTaskStateFactory;

class EmployeeTaskState extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'project_task_id',
        'project_board_id',
        'employee_id',
    ];

    // protected static function newFactory(): EmployeeTaskStateFactory
    // {
    //     // return EmployeeTaskStateFactory::new();
    // }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class,'employee_id');
    }
}
