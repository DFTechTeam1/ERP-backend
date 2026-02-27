<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\EmploymentStatusFactory;

class EmploymentStatus extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'name',
        'is_active',
        'is_terminal', // Indicates if this status is a terminal state (e.g., Terminated, Retired)
    ];

    // protected static function newFactory(): EmploymentStatusFactory
    // {
    //     // return EmploymentStatusFactory::new();
    // }
}
