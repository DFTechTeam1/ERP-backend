<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Development\Database\Factories\DevelopmentTaskProofFactory;

class DevelopmentTaskProof extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'employee_id',
        'nas_path',
    ];

    // protected static function newFactory(): DevelopmentTaskProofFactory
    // {
    //     // return DevelopmentTaskProofFactory::new();
    // }

    public function images(): HasMany
    {
        return $this->hasMany(DevelopmentTaskProofImage::class);
    }
}
