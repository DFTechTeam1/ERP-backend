<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskPicFactory;

class InteractiveProjectTaskPic extends Model
{
    use HasFactory;

    protected $table = 'intr_project_task_pics';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'employee_id',
    ];

    // protected static function newFactory(): InteractiveProjectTaskPicFactory
    // {
    //     // return InteractiveProjectTaskPicFactory::new();
    // }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTask::class, 'task_id');
    }
}
