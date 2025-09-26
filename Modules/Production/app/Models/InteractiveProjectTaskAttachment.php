<?php

namespace Modules\Production\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

// use Modules\Production\Database\Factories\InteractiveProjectTaskAttachmentFactory;

class InteractiveProjectTaskAttachment extends Model
{
    use HasFactory, ModelObserver;

    protected $table = 'intr_project_task_attachments';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'intr_project_task_id',
        'file_path',
    ];

    protected $appends = [
        'real_file_path',
    ];

    // protected static function newFactory(): InteractiveProjectTaskAttachmentFactory
    // {
    //     // return InteractiveProjectTaskAttachmentFactory::new();
    // }

    public function realFilePath(): Attribute
    {
        $output = null;

        if (isset($this->attributes['file_path'])) {
            $output = Storage::disk('public')->exists('interactives/projects/tasks/'.$this->attributes['file_path']) ? asset('storage/interactives/projects/tasks/'.$this->attributes['file_path']) : null;
        }

        return Attribute::make(
            get: fn () => $output
        );
    }
}
