<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Database\Factories\ProjectTaskPicFactory;

class ProjectTaskPic extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_task_id',
        'employee_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }
}
