<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\OutOfSyncEmployeeFactory;

class OutOfSyncEmployee extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'employee_id',
        'greatday_employee_id',
        'position_code',
        'position_name',
        'employment_status',
        'employment_status_code',
        'start_working_date',
        'end_working_date',
        'company_id',
        'address',
        'phone',
        'job_status',
        'work_location_code',
        'cost_center_code',
        'org_unit',
        'employment_start_date',
        'status'
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\Employee\OutOfSyncStatus::class,
        ];
    }

    // protected static function newFactory(): OutOfSyncEmployeeFactory
    // {
    //     // return OutOfSyncEmployeeFactory::new();
    // }
}
