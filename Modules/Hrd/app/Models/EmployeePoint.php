<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;

// use Modules\Hrd\Database\Factories\EmployeePointFactory;

class EmployeePoint extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'total_point',
        'type'
    ];

    // protected static function newFactory(): EmployeePointFactory
    // {
    //     // return EmployeePointFactory::new();
    // }

    public function entertainmentTask(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskSong::class, 'task_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(EmployeePointProject::class, 'employee_point_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
