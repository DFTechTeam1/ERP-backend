<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Production\Models\EntertainmentTaskSong;

// use Modules\Hrd\Database\Factories\EmployeePointFactory;

class EmployeePoint extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'point',
        'additional_point',
        'project_id',
        'task_type',
        'task_id'
    ];

    // protected static function newFactory(): EmployeePointFactory
    // {
    //     // return EmployeePointFactory::new();
    // }

    public function entertainmentTask(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskSong::class, 'task_id');
    }
}
