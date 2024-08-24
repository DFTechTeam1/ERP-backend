<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Hrd\Database\Factories\EmployeeEmergecnyContactFactory;

class EmployeeEmergecnyContact extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    protected static function newFactory(): EmployeeEmergecnyContactFactory
    {
        //return EmployeeEmergecnyContactFactory::new();
    }
}
