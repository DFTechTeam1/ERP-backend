<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use HasFactory;

    protected $table = 'states';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'country_id',
        'name',
        'country_code',
    ];
}
