<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Database\Factories\ProjectTaskLogFactory;

class ProjectTaskLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'project_task_id',
        'type',
        'text',
        'user_id',
    ];

    protected $appends = ['time_created'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\ProjectTask::class, 'project_task_id');
    }

    public function timeCreated(): Attribute
    {
        return Attribute::make(
            get: fn() => date('d F Y', strtotime($this->attributes['created_at'])) . ' at ' . date('H:i', strtotime($this->attributes['created_at']))
        );
    }
}
