<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\ProjectFactory;

class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'client_portal',
        'project_date',
        'event_type',
        'venue',
        'marketing_id',
        'collaboration',
        'note',
        'status',
        'classification',
        'created_by',
        'updated_by',
    ];

    // protected static function newFactory(): ProjectFactory
    // {
    //     //return ProjectFactory::new();
    // }
}
