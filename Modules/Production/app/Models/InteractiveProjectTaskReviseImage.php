<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskReviseImageFactory;

class InteractiveProjectTaskReviseImage extends Model
{
    use HasFactory;

    protected $table = 'intr_project_task_revise_images';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'revise_id',
        'image_path',
    ];

    // protected static function newFactory(): InteractiveProjectTaskReviseImageFactory
    // {
    //     // return InteractiveProjectTaskReviseImageFactory::new();
    // }

    public function revise(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTaskRevise::class, 'revise_id');
    }
}
