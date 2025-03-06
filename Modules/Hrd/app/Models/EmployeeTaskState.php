<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
}
