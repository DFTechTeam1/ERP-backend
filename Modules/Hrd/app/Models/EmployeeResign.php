<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'severance'
    ];

    // protected static function newFactory(): EmployeeResignFactory
    // {
    //     // return EmployeeResignFactory::new();
    // }
}
