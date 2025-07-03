<?php

/**
 * !! NOTE:
 * This table will be filled by cron job every end of month
 */

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Hrd\Database\Factories\EmployeeActiveReportFactory;

class EmployeeActiveReport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'month',
        'year',
        'number_of_employee',
    ];

    // protected static function newFactory(): EmployeeActiveReportFactory
    // {
    //     // return EmployeeActiveReportFactory::new();
    // }
}
