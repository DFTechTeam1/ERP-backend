<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Hrd\Database\Factories\DeleteOfficeEmailQueueFactory;

class DeleteOfficeEmailQueue extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'email',
        'status',
    ];

    // protected static function newFactory(): DeleteOfficeEmailQueueFactory
    // {
    //     // return DeleteOfficeEmailQueueFactory::new();
    // }
}
