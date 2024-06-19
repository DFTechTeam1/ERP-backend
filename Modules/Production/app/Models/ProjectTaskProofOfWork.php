<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    ];

    protected $appends = ['images'];

    public function images(): Attribute
    {
        $out = [];
        if (isset($this->attributes['preview_image'])) {
            $out = json_decode($this->attributes['preview_image'], true);
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
}
