<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\ProjectTask;

// use Modules\Hrd\Database\Factories\EmployeePointDetailFactory;

class EmployeePointDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_point_id',
        'type',
        'task_id',
        'point',
    ];

    // protected static function newFactory(): EmployeePointDetailFactory
    // {
    //     // return EmployeePointDetailFactory::new();
    // }

    public function entertainmentTask(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskSong::class, 'task_id');
    }

    public function productionTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }
}
