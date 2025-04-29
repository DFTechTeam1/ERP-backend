<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Hrd\Database\Factories\EmployeeTimeoffFactory;

class EmployeeTimeoff extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'time_off_id',
        'talenta_user_id',
        'policy_name',
        'request_type',
        'file_url',
        'start_date',
        'end_date',
        'status'
    ];

    // protected static function newFactory(): EmployeeTimeoffFactory
    // {
    //     // return EmployeeTimeoffFactory::new();
    // }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'talenta_user_id', 'talenta_user_id');
    }
}
