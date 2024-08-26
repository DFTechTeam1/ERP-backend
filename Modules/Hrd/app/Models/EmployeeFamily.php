<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Hrd\Database\Factories\EmployeeFamilyFactory;
use \App\Traits\ModelObserver;

class EmployeeFamily extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'uid',
        'id_number',
        'relation',
        'date_of_birth',
        'gender',
        'job',
        'employee_id'
    ];
}
