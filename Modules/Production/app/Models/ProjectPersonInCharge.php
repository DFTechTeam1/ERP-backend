<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\ProjectPersonInChargeFactory;

class ProjectPersonInCharge extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'pic_id',
    ];

    // protected static function newFactory(): ProjectPersonInChargeFactory
    // {
    //     //return ProjectPersonInChargeFactory::new();
    // }
}
