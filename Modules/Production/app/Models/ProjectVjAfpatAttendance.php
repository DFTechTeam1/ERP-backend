<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProjectVjAfpatAttendanceFactory;

class ProjectVjAfpatAttendance extends Model
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

    // protected static function newFactory(): ProjectVjAfpatAttendanceFactory
    // {
    //     // return ProjectVjAfpatAttendanceFactory::new();
    // }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id', 'id');
    }
}
