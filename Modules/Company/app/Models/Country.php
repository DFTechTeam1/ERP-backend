<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'iso3',
        'iso2',
        'phone_code',
        'currency',
    ];
}
