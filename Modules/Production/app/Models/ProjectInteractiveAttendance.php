<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProjectInteractiveAttendanceFactory;

class ProjectInteractiveAttendance extends Model
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

    // protected static function newFactory(): ProjectInteractiveAttendanceFactory
    // {
    //     // return ProjectInteractiveAttendanceFactory::new();
    // }
}
