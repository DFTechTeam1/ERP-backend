<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'pic_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\Project::class, 'project_id');
    }

    // protected static function newFactory(): ProjectPersonInChargeFactory
    // {
    //     //return ProjectPersonInChargeFactory::new();
    // }
}
