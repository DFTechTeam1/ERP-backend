<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

// use Modules\Hrd\Database\Factories\EmployeeTransferHistoryFactory;

class EmployeeTransferHistory extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'from_position_id',
        'from_position_name',
        'to_position_id',
        'to_position_name',
        'transfer_type',
        'from_work_location_id',
        'from_work_location_name',
        'to_work_location_id',
        'to_work_location_name',
        'from_cost_center_id',
        'from_cost_Center_name',
        'to_cost_center_id',
        'to_cost_center_name',
        'to_boos_id',
        'to_boss_name',
        'from_boss_id',
        'from_boss_name',
        'to_employment_status_name',
        'to_employment_status_id',
        'from_employment_status_name',
        'from_employment_status_id',
        'status',
        'effective_date',
        'transferred_by',
        'note',
    ];

    // protected static function newFactory(): EmployeeTransferHistoryFactory
    // {
    //     // return EmployeeTransferHistoryFactory::new();
    // }
}
