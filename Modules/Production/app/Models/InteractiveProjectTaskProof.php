<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskProofFactory;

class InteractiveProjectTaskProof extends Model
{
    use HasFactory;

    protected $table = 'intr_task_proofs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'employee_id',
        'nas_path',
    ];

    // protected static function newFactory(): InteractiveProjectTaskProofFactory
    // {
    //     // return InteractiveProjectTaskProofFactory::new();
    // }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InteractiveProjectTask::class, 'task_id');
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }

    public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InteractiveProjectTaskProofImage::class, 'intr_task_proof_id');
    }
}
