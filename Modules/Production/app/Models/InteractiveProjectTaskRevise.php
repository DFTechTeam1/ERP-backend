<?php

namespace Modules\Production\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskReviseFactory;

class InteractiveProjectTaskRevise extends Model
{
    use HasFactory;

    protected $table = 'intr_project_task_revises';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'reason',
        'assigned_by',
    ];

    // protected static function newFactory(): InteractiveProjectTaskReviseFactory
    // {
    //     // return InteractiveProjectTaskReviseFactory::new();
    // }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTask::class, 'task_id');
    }

    public function assignedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InteractiveProjectTaskReviseImage::class, 'revise_id');
    }
}
