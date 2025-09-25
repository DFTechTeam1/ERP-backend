<?php

namespace Modules\Production\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    // protected static function newFactory(): InteractiveProjectTaskAttachmentFactory
    // {
    //     // return InteractiveProjectTaskAttachmentFactory::new();
    // }
}
