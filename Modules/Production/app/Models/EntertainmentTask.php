<?php

namespace Modules\Production\Models;

use App\Models\User;
use App\Traits\EntertainmentLogWatch;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntertainmentTask extends Model
{
    use HasFactory, EntertainmentLogWatch, ModelObserver;

    protected $table = 'entertainment_tasks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'type',
        'uid',
        'name',
        'description',
        'deadline',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'status' => 'integer',
        ];
    }

    public function pics(): HasMany
    {
        return $this->hasMany(EntertainmentTaskPic::class, 'task_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function songItems(): HasMany
    {
        return $this->hasMany(EntertainmentTaskSongItem::class, 'entertainment_task_id');
    }

    public function deadlineHistories(): HasMany
    {
        return $this->hasMany(EntertainmentTaskDeadlineHistory::class, 'task_id');
    }

    public function proofOfWorks(): HasMany
    {
        return $this->hasMany(EntertainmentTaskProofOfWork::class, 'task_id');
    }

    public function workStates(): HasMany
    {
        return $this->hasMany(EntertainmentTaskPicWorkstate::class, 'task_id');
    }

    public function approvalStates(): HasMany
    {
        return $this->hasMany(EntertainmentTaskPicApprovalstate::class, 'task_id');
    }

    public function holdStates(): HasMany
    {
        return $this->hasMany(EntertainmentTaskPicHoldstate::class, 'task_id');
    }

    public function reviseStates(): HasMany
    {
        return $this->hasMany(EntertainmentTaskPicRevisestate::class, 'task_id');
    }

    public function durationHistories(): HasMany
    {
        return $this->hasMany(EntertainmentTaskDurationHistory::class, 'task_id');
    }
}
