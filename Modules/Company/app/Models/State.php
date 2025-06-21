<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\Database\Factories\StateFactory;


class State extends Model
{
    use HasFactory;

    protected $table = 'states';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'country_id',
        'name',
        'country_code',
    ];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'state_id');
    }
}
