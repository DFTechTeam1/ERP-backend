<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Hrd\Models\Employee;

// use Modules\Production\Database\Factories\ProjectMarcommAttendanceFactory;

class ProjectMarcommAttendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'employee_id',
        'note',
    ];

    // protected static function newFactory(): ProjectMarcommAttendanceFactory
    // {
    //     // return ProjectMarcommAttendanceFactory::new();
    // }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
