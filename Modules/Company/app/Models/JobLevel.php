<?php

namespace Modules\Company\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Company\Database\Factories\JobLevelFactory;

class JobLevel extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'created_by',
        'updated_by',
    ];

    // protected static function newFactory(): JobLevelFactory
    // {
    //     // return JobLevelFactory::new();
    // }
}
