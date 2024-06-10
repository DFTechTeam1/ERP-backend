<?php

namespace Modules\Production\Models;

use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'task_type',
    ];

    protected $appends = ['task_type_text', 'task_type_color', 'start_date_text', 'end_date_text'];

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

    public function medias(): HasMany
    {
        return $this->hasMany(ProjectTaskAttachment::class, 'project_task_id')
            ->where('type', \App\Enums\Production\ProjectTaskAttachment::Media->value)
            ->orWhere('type', \App\Enums\Production\ProjectTaskAttachment::ExternalLink->value);
    }

    public function taskLink(): HasMany
    {
        return $this->hasMany(ProjectTaskAttachment::class, 'project_task_id')
            ->where('type', \App\Enums\Production\ProjectTaskAttachment::TaskLink->value);
    }

    public function taskTypeText(): Attribute
    {
        $out = '';
        if ($this->task_type) {
            $cases = \App\Enums\Production\TaskType::cases();
            foreach ($cases as $case) {
                if ($case->value == $this->task_type) {
                    $out = $case->label();
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function taskTypeColor(): Attribute
    {
        $out = '';
        if ($this->task_type) {
            $cases = \App\Enums\Production\TaskType::cases();
            foreach ($cases as $case) {
                if ($case->value == $this->task_type) {
                    $out = $case->color();
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function startDateText(): Attribute
    {
        $out = '-';
        if ($this->start_date) {
            $out = date('d F Y', strtotime($this->start_date));
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
    
    public function endDateText(): Attribute
    {
        $out = '-';
        if ($this->end_date) {
            $out = date('d F Y', strtotime($this->end_date));
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
}
