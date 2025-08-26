<?php

namespace Modules\Development\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
// use Modules\Development\Database\Factories\DevelopmentProjectTaskAttachmentFactory;

class DevelopmentProjectTaskAttachment extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'task_id',
        'file_path'
    ];

    protected $appends = [
        'real_file_path'
    ];

    // protected static function newFactory(): DevelopmentProjectTaskAttachmentFactory
    // {
    //     // return DevelopmentProjectTaskAttachmentFactory::new();
    // }

    public function realFilePath(): Attribute
    {
        $output = null;

        if (isset($this->attributes['file_path'])) {
            $output = Storage::disk('public')->exists('development/projects/tasks/' . $this->attributes['file_path']) ? asset('storage/development/projects/tasks/' . $this->attributes['file_path']) : null
            ;
        }
        return Attribute::make(
            get: fn () => $output
        );
    }
}
