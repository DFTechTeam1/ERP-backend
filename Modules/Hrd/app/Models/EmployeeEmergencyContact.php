<?php

namespace Modules\Hrd\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Hrd\Database\Factories\EmployeeEmergencyContactFactory;

class EmployeeEmergencyContact extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'phone',
        'relation',
        'uid',
        'employee_id',
    ];
}
