<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Database\Factories\ProjectTaskProofOfWorkFactory;

class ProjectTaskProofOfWork extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_task_id',
        'project_id',
        'nas_link',
        'preview_image',
        'created_by',
        'updated_by',
        'created_year',
        'created_month',
    ];

    protected $appends = ['images'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\ProjectTask::class, 'project_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function images(): Attribute
    {
        $out = [];
        if (isset($this->attributes['preview_image'])) {
            $out = json_decode($this->attributes['preview_image'], true);
            $projectId = $this->attributes['project_id'];
            $taskId = $this->attributes['project_task_id'];
            $out = collect($out)->map(function ($item) use ($projectId, $taskId) {
                $item = asset("storage/projects/{$projectId}/task/{$taskId}/proofOfWork/{$item}");

                return $item;
            })->toArray();
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
}
