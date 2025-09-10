<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Development\Database\Factories\DevelopmentTaskProofImageFactory;

class DevelopmentTaskProofImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'development_task_proof_id',
        'image_path',
    ];

    protected $appends = [
        'real_image_path',
    ];

    // protected static function newFactory(): DevelopmentTaskProofImageFactory
    // {
    //     // return DevelopmentTaskProofImageFactory::new();
    // }

    public function realImagePath(): Attribute
    {
        $output = null;

        if (isset($this->attributes['image_path'])) {
            $output = asset('storage/development/projects/tasks/proofs/'.$this->attributes['image_path']);
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }
}
