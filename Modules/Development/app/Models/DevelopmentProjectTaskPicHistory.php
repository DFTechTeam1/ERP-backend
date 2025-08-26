<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

// use Modules\Development\Database\Factories\DevelopmentProjectTaskPicHistoryFactory;

class DevelopmentProjectTaskPicHistory extends Model
{
    use HasFactory;

    protected $table = 'dev_project_task_pic_histories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'employee_id',
        'is_until_finish'
    ];

    // protected static function newFactory(): DevelopmentProjectTaskPicHistoryFactory
    // {
    //     // return DevelopmentProjectTaskPicHistoryFactory::new();
    // }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProjectTask::class, 'task_id');
    }
}
