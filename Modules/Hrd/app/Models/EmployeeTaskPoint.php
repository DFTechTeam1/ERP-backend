<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Hrd\Database\Factories\EmployeeTaskPointFactory;

class EmployeeTaskPoint extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'point',
        'additional_point',
        'total_task',
        'project_id',
        'created_by',
    ];
}
