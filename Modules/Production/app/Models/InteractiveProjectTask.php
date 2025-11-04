<?php

namespace Modules\Production\Models;

use App\Enums\Interactive\InteractiveTaskStatus;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Production\Database\Factories\InteractiveProjectTaskFactory;

// use Modules\Production\Database\Factories\InteractiveProjectTaskFactory;

class InteractiveProjectTask extends Model
{
    use HasFactory, ModelObserver;

    protected $table = 'intr_project_tasks';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'intr_project_id',
        'intr_project_board_id',
        'name',
        'description',
        'deadline',
        'status',
        'current_pic_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => InteractiveTaskStatus::class,
        ];
    }

    protected static function newFactory(): InteractiveProjectTaskFactory
    {
        return InteractiveProjectTaskFactory::new();
    }

    public function board(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectBoard::class, 'intr_project_board_id');
    }

    public function interactiveProject()
    {
        return $this->belongsTo(InteractiveProject::class, 'intr_project_id');
    }

    public function interactiveProjectBoard()
    {
        return $this->belongsTo(InteractiveProjectBoard::class, 'intr_project_board_id');
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskDeadline::class, 'task_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskAttachment::class, 'intr_project_task_id');
    }

    public function pics(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskPic::class, 'task_id');
    }

    public function picHistories(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskPicHistory::class, 'task_id');
    }

    public function taskProofs(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskProof::class, 'task_id');
    }

    public function revises(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskRevise::class, 'task_id');
    }

    public function workStates(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskPicWorkstate::class, 'task_id');
    }

    public function onGoingWorkStates(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskPicWorkstate::class, 'task_id')
            ->whereNull('complete_at');
    }

    public function holdStates(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskPicHoldstate::class, 'task_id');
    }

    public function reviseStates(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskRevisestate::class, 'task_id');
    }

    public function approvalStates(): HasMany
    {
        return $this->hasMany(InteractiveProjectTaskApprovalState::class, 'task_id');
    }
}
