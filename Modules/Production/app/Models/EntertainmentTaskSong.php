<?php

namespace Modules\Production\Models;

use App\Enums\Production\TaskSongStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Hrd\Models\Employee;
use Modules\Production\Database\Factories\EntertainmentTaskSongFactory;

// use Modules\Production\Database\Factories\EntertainmentTaskSongFactory;

class EntertainmentTaskSong extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_song_list_id',
        'employee_id',
        'project_id',
        'status',
        'time_tracker',
    ];

    protected $appends = ['status_text', 'status_color'];

    protected static function newFactory(): EntertainmentTaskSongFactory
    {
        return EntertainmentTaskSongFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(ProjectSongList::class, 'project_song_list_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function revises(): HasMany
    {
        return $this->hasMany(EntertainmentTaskSongRevise::class, 'entertainment_task_song_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(EntertainmentTaskSongResult::class, 'task_id');
    }

    public function timeTracker(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => empty($value) ? null : json_encode($value),
            get: fn ($value) => $value ? json_decode($value, true) : []
        );
    }

    public function statusText(): Attribute
    {
        $output = __('global.waitingToDistribute');
        if (isset($this->attributes['status'])) {
            $output = TaskSongStatus::getLabel($this->attributes['status']);
        }

        return Attribute::make(
            get: fn () => $output
        );
    }

    public function statusColor(): Attribute
    {
        $output = 'info';

        if (isset($this->attributes['status'])) {
            $output = TaskSongStatus::getColor($this->attributes['status']);
        }

        return Attribute::make(
            get: fn () => $output
        );
    }
}
