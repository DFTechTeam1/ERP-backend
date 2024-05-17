<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Hrd\Database\factories\JobFactory;

class Job extends Model
{
    use HasFactory;

    protected $table = 'vacancies';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];
    
    protected static function newFactory(): JobFactory
    {
        //return JobFactory::new();
    }
}
