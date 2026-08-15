<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProjectLeadQueueFactory;

class ProjectLeadQueue extends Model
{
    use HasFactory;

    protected $table = 'project_lead_queue';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'lead_id',
        'status',
        'message'
    ];

    // protected static function newFactory(): ProjectLeadQueueFactory
    // {
    //     // return ProjectLeadQueueFactory::new();
    // }
}
