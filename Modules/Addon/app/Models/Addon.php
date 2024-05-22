<?php

namespace Modules\Addon\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Addon\Database\Factories\AddonFactory;

class Addon extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'preview_img',
        'tutorial_video',
        'main_file',
    ];

    protected $appends = ['preview_img_path'];

    public function previewImgPath(): Attribute
    {
        $out = '';
        if ($this->preview_img) {
            $out = env('APP_URL') . '/storage/addons/' . $this->preview_img;
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    protected static function newFactory(): AddonFactory
    {
        //return AddonFactory::new();
    }
}
