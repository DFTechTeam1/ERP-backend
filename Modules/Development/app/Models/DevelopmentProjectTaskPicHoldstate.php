<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Development\Database\Factories\DevelopmentProjectTaskPicHoldstateFactory;

class DevelopmentProjectTaskPicHoldstate extends Model
{
    use HasFactory;

    protected $table = 'dev_project_task_pic_holdstates';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'holded_at',
        'unholded_at',
        'task_id',
        'employee_id',
        'work_state_id',
    ];

    // protected static function newFactory(): DevelopmentProjectTaskPicHoldstateFactory
    // {
    //     // return DevelopmentProjectTaskPicHoldstateFactory::new();
    // }
}
