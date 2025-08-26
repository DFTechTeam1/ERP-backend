<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Development\Database\Factories\DevelopmentProjectTaskDeadlineFactory;

class DevelopmentProjectTaskDeadline extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'deadline',
        'start_time',
        'actual_end_time',
        'employee_id'
    ];

    // protected static function newFactory(): DevelopmentProjectTaskDeadlineFactory
    // {
    //     // return DevelopmentProjectTaskDeadlineFactory::new();
    // }
}
