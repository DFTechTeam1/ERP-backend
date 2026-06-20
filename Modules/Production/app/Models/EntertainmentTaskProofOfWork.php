<?php

namespace Modules\Production\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentTaskProofOfWork extends Model
{
    use HasFactory;

    protected $table = 'entertainment_task_proof_of_works';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_id',
        'nas_path',
        'file_path',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_path' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTask::class, 'task_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
