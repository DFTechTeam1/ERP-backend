<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
// use Modules\Development\Database\Factories\DevelopmentProjectTaskReviseImageFactory;

class DevelopmentProjectTaskReviseImage extends Model
{
    use HasFactory;

    protected $table = 'dev_project_task_revise_images';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'revise_id',
        'image_path'
    ];

    protected $appends = [
        'real_image_path'
    ];

    // protected static function newFactory(): DevelopmentProjectTaskReviseImageFactory
    // {
    //     // return DevelopmentProjectTaskReviseImageFactory::new();
    // }

    public function realImagePath(): Attribute
    {
        $output = null;

        if (isset($this->attributes['image_path'])) {
            $output = asset('storage/development/projects/tasks/revises/' . $this->attributes['image_path']);
        }

        return Attribute::make(
            get: fn () => $output
        );
    }
}
