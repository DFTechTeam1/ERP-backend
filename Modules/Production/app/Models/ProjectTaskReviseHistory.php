<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\ProjectTaskReviseHistoryFactory;

class ProjectTaskReviseHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_task_id',
        'project_id',
        'selected_user',
        'reason',
        'file',
        'revise_by',
    ];

    protected $appends = ['full_path', 'revise_at'];

    public function fullPath(): Attribute
    {
        $out = '-';

        if (isset($this->attributes['file'])) {
            $projectId = $this->attributes['project_id'];
            $taskId = $this->attributes['project_task_id'];
            $file = $this->attributes['file'];
            $out = asset("storage/projects/{$projectId}/task/{$taskId}/revise/{$file}");
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function reviseAt(): Attribute
    {
        return Attribute::make(
            get: fn() => date('d F Y H:i', strtotime($this->attributes['created_at'])),
        );
    }
}
