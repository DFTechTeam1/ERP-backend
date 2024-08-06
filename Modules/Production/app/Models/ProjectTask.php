<?php

namespace Modules\Production\Models;

use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Carbon\Carbon;
use DateTime;
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
        'performance_time',
        'status',
        'current_pics',
        'current_board',
        'is_approved',
    ];

    protected $appends = ['task_type_text', 'task_type_color', 'start_date_text', 'end_date_text', 'performance_recap', 'proof_of_works_detail', 'task_status', 'task_status_color'];

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

    public function revises(): HasMany
    {
        return $this->hasMany(ProjectTaskReviseHistory::class, 'project_task_id');
    }

    public function medias(): HasMany
    {
        return $this->hasMany(ProjectTaskAttachment::class, 'project_task_id')
            ->where('type', \App\Enums\Production\ProjectTaskAttachment::Media->value)
            ->orWhere('type', \App\Enums\Production\ProjectTaskAttachment::ExternalLink->value);
    }

    public function proofOfWorks(): HasMany
    {
        return $this->hasMany(ProjectTaskProofOfWork::class, 'project_task_id')
            ->orderBy('created_at', 'DESC');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectTaskLog::class, 'project_task_id')
            ->orderBy('created_at', 'DESC');
    }

    public function times(): HasMany
    {
        return $this->hasMany(\Modules\Production\Models\ProjectTaskPicLog::class, 'project_task_id')
            ->orderBy('created_at', 'ASC');
    }

    public function taskLink(): HasMany
    {
        return $this->hasMany(ProjectTaskAttachment::class, 'project_task_id')
            ->where('type', \App\Enums\Production\ProjectTaskAttachment::TaskLink->value);
    }

    public function proofOfWorksDetail(): Attribute
    {
        $out = null;
        if (count($this->proofOfWorks) > 0) {
            $out = collect($this->proofOfWorks)->groupBy('created_at')->all();
        }

        return Attribute::make(
            get: fn () => $out,
        );
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

    public function performanceRecap(): Attribute
    {
        $out = null;
        if ((isset($this->attributes['performance_time'])) && ($this->attributes['performance_time'])) {
            $performance = json_decode($this->attributes['performance_time'], true);
            logging('performance', $performance);
            if (count($performance) > 0) {
                $out = [];
                foreach ($performance as $report) {
                    $start = new DateTime($report['start_at']);
                    $end = $report['end_at'] ? new DateTime($report['end_at']) : new DateTime('now');
                    $diff = date_diff($start, $end);
                    $day = $diff->d > 0 ? $diff->d . ' ' . __('global.day') : null;
                    $hour = $diff->h > 0 ? $diff->h . ' ' . __('global.hours') : null;
                    $minute = $diff->i > 0 ? $diff->i . ' ' . __('global.minutes') : null;
                    $workTime = $minute;
                    if ($hour) {
                        $workTime = $hour . ' ' . __('global.and') . ' ' . $minute;
                    }
                    if ($day) {
                        $workTime = $day . ' ' . $hour . ' ' . __('global.and') . ' ' . $minute;
                    }

                    $out[] = [
                        'type' => $report['type'],
                        'start' => date('d F Y H:i', strtotime($report['start_at'])),
                        'end' => date('d F Y H:i', strtotime($report['end_at'])),
                        'worktime' => $workTime
                    ]; 
                }
            }
        }

        return Attribute::make(
            get: fn() => $out
        );
    }

    public function haveTaskPic()
    {
        if (isset($this->attributes['id'])) {
        }
        $out = false;

        if ($this->pics()->count() > 0) {
            $out = true;
        }

        return $out;
    }

    public function taskStatus(): Attribute
    {
        $out = null;

        if (isset($this->attributes['status'])) {
            $taskStatus = \App\Enums\Production\TaskStatus::cases();
            
            foreach ($taskStatus as $status) {
                if ($status->value == $this->attributes['status']) {
                    $out = $status->label();

                    break;
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function taskStatusColor(): Attribute
    {
        $out = null;

        if (isset($this->attributes['status'])) {
            $taskStatus = \App\Enums\Production\TaskStatus::cases();

            foreach ($taskStatus as $status) {
                if ($status->value == $this->attributes['status']) {
                    $out = $status->color();

                    break;
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
}
