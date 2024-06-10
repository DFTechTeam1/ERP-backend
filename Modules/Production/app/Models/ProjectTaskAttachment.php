<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\ProjectTaskAttachmentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class ProjectTaskAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_task_id',
        'project_id',
        'media',
        'display_name',
        'related_task_id',
        'type',
    ];

    protected $appends = ['media_link', 'media_type', 'ext', 'task_link_detail', 'update_timing'];

    public function mediaType(): Attribute
    {
        $out = '';

        if ($this->attributes['type'] == \App\Enums\Production\ProjectTaskAttachment::Media->value) {
            $out = 'media';
        } else if ($this->attributes['type'] == \App\Enums\Production\ProjectTaskAttachment::ExternalLink->value) {
            $out = 'link';
        } else {
            $out = 'task';
        }
        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function ext(): Attribute
    {
        $out = '';
        if ($this->attributes['type'] == \App\Enums\Production\ProjectTaskAttachment::Media->value) {
            $name = explode('.', $this->attributes['media']);
            $out = array_pop($name);
        }
        
        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function mediaLink(): Attribute
    {
        $out = '';

        if ($this->attributes['type'] == \App\Enums\Production\ProjectTaskAttachment::Media->value) {
            $out = asset('storage/projects/' . $this->project_id . '/task/' . $this->project_task_id . '/' . $this->attributes['media']);
        } else if ($this->attributes['type'] == \App\Enums\Production\ProjectTaskAttachment::ExternalLink->value) {
            $out = $this->attributes['media'];
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function taskLinkDetail(): Attribute
    {
        $out = null;
        if (isset($this->attributes['type'])) {
            if ($this->attributes['type'] == \App\Enums\Production\ProjectTaskAttachment::TaskLink->value) {
                $out = \Modules\Production\Models\ProjectTask::selectRaw('id,name,uid')
                    ->find($this->attributes['media']);
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function updateTiming(): Attribute
    {
        $out = '';
        if (isset($this->attributes['updated_at'])) {
            $out = Carbon::parse($this->updated_at)->diffForHumans() . ' ' . __('global.at') . ' ' . date("H:i", strtotime($this->attributes['updated_at']));
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
}
