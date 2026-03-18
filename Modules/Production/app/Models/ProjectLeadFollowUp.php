<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProjectLeadFollowUpFactory;

class ProjectLeadFollowUp extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_lead_id',
        'follow_up_date',
        'customer_phone',
        'message',
        'created_by'
    ];

    // protected static function newFactory(): ProjectLeadFollowUpFactory
    // {
    //     // return ProjectLeadFollowUpFactory::new();
    // }
}
