<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Hrd\Database\factories\EmployeeSignFactory;

class EmployeeSign extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'employee_id',
        'sign',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    //    protected static function newFactory(): EmployeeSignFactory
    //    {
    //        //return EmployeeSignFactory::new();
    //    }
}
