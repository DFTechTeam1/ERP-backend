<?php

namespace Modules\Hrd\Models;

use App\Enums\Employee\OutOfSyncStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\Models\PositionBackup;

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
        'nickname',
        'id_number',
        'gender',
        'birth_date',
        'birth_place',
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
        'grade_code',
        'bank_code',
        'bank_account',
        'bank_account_name',
        'org_unit',
        'employment_start_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => OutOfSyncStatus::class,
            'birth_date' => 'date',
            'start_working_date' => 'datetime',
            'end_working_date' => 'datetime',
            'employment_start_date' => 'datetime',
        ];
    }

    /**
     * The ERP position mirrored from Greatday, matched on the Greatday position code.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(PositionBackup::class, 'position_code', 'greatday_code');
    }

    // protected static function newFactory(): OutOfSyncEmployeeFactory
    // {
    //     // return OutOfSyncEmployeeFactory::new();
    // }
}
