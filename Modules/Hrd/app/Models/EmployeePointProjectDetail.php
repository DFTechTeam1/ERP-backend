<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\ProjectTask;

// use Modules\Hrd\Database\Factories\EmployeePointProjectDetailFactory;

class EmployeePointProjectDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'point_id',
    ];

    // protected static function newFactory(): EmployeePointProjectDetailFactory
    // {
    //     // return EmployeePointProjectDetailFactory::new();
    // }

    public function productionTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function entertainmentTask(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskSong::class, 'task_id', 'id');
    }
}
