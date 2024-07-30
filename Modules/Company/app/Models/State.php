<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Company\Database\Factories\StateFactory;

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
