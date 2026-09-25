<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\ProjectActivityFactory;

class ProjectActivity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'action',
        'actor',
    ];

    // protected static function newFactory(): ProjectActivityFactory
    // {
    //     // return ProjectActivityFactory::new();
    // }
}
