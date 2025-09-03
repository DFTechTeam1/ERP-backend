<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Hrd\Database\Factories\EmployeeResignFactory;

class EmployeeResign extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'reason',
        'resign_date',
        'severance',
        'current_position_id',
        'current_employee_status'
    ];

    // protected static function newFactory(): EmployeeResignFactory
    // {
    //     // return EmployeeResignFactory::new();
    // }
}
