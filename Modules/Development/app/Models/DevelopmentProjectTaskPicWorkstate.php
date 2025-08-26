<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Development\Database\Factories\DevelopmentProjectTaskPicWorkstateFactory;

class DevelopmentProjectTaskPicWorkstate extends Model
{
    use HasFactory;

    protected $table = 'dev_project_task_pic_workstates';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'started_at',
        'finished_at',
        'task_id',
        'employee_id'
    ];

    // protected static function newFactory(): DevelopmentProjectTaskPicWorkstateFactory
    // {
    //     // return DevelopmentProjectTaskPicWorkstateFactory::new();
    // }

    public function holdStates(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTaskPicHoldstate::class, 'work_state_id');
    }
}
