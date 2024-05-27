<?php

namespace Modules\Production\Models;

use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Production\Database\Factories\ProjectTaskFactory;

class ProjectTask extends Model
{
    use HasFactory, ModelObserver, ModelCreationObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'project_id',
        'project_board_id',
        'start_date',
        'end_date',
        'description',
        'name',
        'start_working_at',
        'created_by',
        'updated_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(ProjectBoard::class, 'project_board_id');
    }

    public function pics(): HasMany
    {
        return $this->hasMany(ProjectTaskPic::class, 'project_task_id');
    }
}
