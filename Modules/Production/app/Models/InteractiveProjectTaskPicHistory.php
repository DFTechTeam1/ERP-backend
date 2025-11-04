<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskPicHistoryFactory;

class InteractiveProjectTaskPicHistory extends Model
{
    use HasFactory;

    protected $table = 'intr_project_task_pic_histories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'employee_id',
        'is_until_finish',
    ];

    // protected static function newFactory(): InteractiveProjectTaskPicHistoryFactory
    // {
    //     // return InteractiveProjectTaskPicHistoryFactory::new();
    // }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTask::class, 'task_id');
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }
}
