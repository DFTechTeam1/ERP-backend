<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\EmployeeTransferHistoryFactory;

class EmployeeTransferHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): EmployeeTransferHistoryFactory
    // {
    //     // return EmployeeTransferHistoryFactory::new();
    // }
}
