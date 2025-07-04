<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Production\Database\Factories\CustomerFactory;

// use Modules\Production\Database\Factories\CustomerFactory;

class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
    ];

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
