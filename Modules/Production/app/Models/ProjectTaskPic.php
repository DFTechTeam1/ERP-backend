<?php

namespace Modules\Production\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTaskPic extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_task_id',
        'employee_id',
        'status',
        'approved_at',
        'assigned_at',
        'assigned_by'
    ];

    protected $appends = ['status_text', 'is_active'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'employee_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\ProjectTask::class, 'project_task_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(\Modules\Production\Models\ProjectTaskPicLog::class, 'employee_id', 'employee_id');
    }

    public function statusText(): Attribute
    {
        $out = '';
        if (isset($this->attributes['status'])) {
            $cases = \App\Enums\Production\TaskPicStatus::cases();

            foreach ($cases as $case) {
                if ($case->value == $this->attributes['status']) {
                    $out = $case->label();
                }
            }
        }

        return Attribute::make(
            get: fn () => $out,
        );
    }

    public function isActive(): Attribute
    {
        $out = false;
        if (isset($this->attributes['status'])) {
            $out = $this->attributes['status'] == \App\Enums\Production\TaskPicStatus::Approved->value || $this->attributes['status'] == \App\Enums\Production\TaskPicStatus::Revise->value ? true : false;
        }

        return Attribute::make(
            get: fn () => $out,
        );
    }
}
