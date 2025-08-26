<?php

namespace Modules\Development\Models;

use App\Enums\Development\Project\ProjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Development\Database\Factories\DevelopmentProjectTaskFactory;
use App\Traits\ModelObserver;

// use Modules\Development\Database\Factories\DevelopmentProjectTaskFactory;

class DevelopmentProjectTask extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'development_project_id',
        'development_project_board_id',
        'name',
        'description',
        'deadline',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\Development\Project\Task\TaskStatus::class,
        ];
    }

    protected static function newFactory(): DevelopmentProjectTaskFactory
    {
        return DevelopmentProjectTaskFactory::new();
    }

    public function developmentProject(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'development_project_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProjectBoard::class, 'development_project_board_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTaskAttachment::class, 'task_id');
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTaskDeadline::class, 'task_id');
    }

    public function pics(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTaskPic::class, 'task_id');
    }

    public function picHistories(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTaskPicHistory::class, 'task_id');
    }

    public function workStates(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTaskPicWorkstate::class, 'task_id');
    }

    public function holdStates(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTaskPicHoldstate::class, 'task_id');
    }

    public function taskProofs(): HasMany
    {
        return $this->hasMany(DevelopmentTaskProof::class, 'task_id');
    }

    public function revises(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTaskRevise::class, 'task_id');
    }
}