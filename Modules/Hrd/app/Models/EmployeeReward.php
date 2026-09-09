<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Production\Models\Project;

// use Modules\Hrd\Database\Factories\EmployeeRewardFactory;

class EmployeeReward extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'project_id',
        'employee_point_project_id',
        'base_reward',
        'total_point',
        'point',
        'additional_point',
        'total_reward',
        'project_class_name'
    ];

    // protected static function newFactory(): EmployeeRewardFactory
    // {
    //     // return EmployeeRewardFactory::new();
    // }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
