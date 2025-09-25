<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskProofImageFactory;

class InteractiveProjectTaskProofImage extends Model
{
    use HasFactory;

    protected $table = 'intr_task_proof_images';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'intr_task_proof_id',
        'image_path',
    ];

    // protected static function newFactory(): InteractiveProjectTaskProofImageFactory
    // {
    //     // return InteractiveProjectTaskProofImageFactory::new();
    // }

    protected $appends = [
        'real_image_path',
    ];

    public function realImagePath(): Attribute
    {
        $output = null;

        if (isset($this->attributes['image_path'])) {
            $output = asset('storage/interactives/projects/tasks/proofs/'.$this->attributes['image_path']);
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }

    public function taskProof(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTaskProof::class, 'intr_task_proof_id');
    }
}
