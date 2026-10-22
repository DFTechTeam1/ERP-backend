<?php

namespace Modules\Hrd\Models;

use App\Enums\Hrd\PointTaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\ProjectTask;

// use Modules\Hrd\Database\Factories\EmployeePointProjectDetailFactory;

class EmployeePointProjectDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'task_type',
        'point_id',
    ];

    // protected static function newFactory(): EmployeePointProjectDetailFactory
    // {
    //     // return EmployeePointProjectDetailFactory::new();
    // }

    public function productionTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function entertainmentTask(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskSong::class, 'task_id', 'id');
    }

    /**
     * Resolve the task this detail points to, using the stored task_type so the
     * overlapping-id ambiguity between project_tasks and entertainment_task_songs
     * can never pick the wrong table.
     *
     * For rows not yet backfilled (task_type is null) it falls back to accepting
     * the task that belongs to $projectIdForFallback — the pre-existing project
     * scoped heuristic — so reports stay correct during the backfill rollout.
     *
     * @return ProjectTask|EntertainmentTaskSong|null
     */
    public function resolveTask(?int $projectIdForFallback = null): ?Model
    {
        if ($this->task_type === PointTaskType::Production->value) {
            return $this->productionTask;
        }
        if ($this->task_type === PointTaskType::Entertainment->value) {
            return $this->entertainmentTask;
        }

        if ($projectIdForFallback !== null) {
            if ($this->productionTask && (int) $this->productionTask->project_id === $projectIdForFallback) {
                return $this->productionTask;
            }
            if ($this->entertainmentTask && (int) $this->entertainmentTask->project_id === $projectIdForFallback) {
                return $this->entertainmentTask;
            }
        }

        return null;
    }

    /**
     * The display name of the resolved task (song name for entertainment).
     */
    public function resolveTaskName(?int $projectIdForFallback = null): ?string
    {
        $task = $this->resolveTask($projectIdForFallback);

        if ($task instanceof EntertainmentTaskSong) {
            return $task->song?->name;
        }

        return $task?->name;
    }
}
