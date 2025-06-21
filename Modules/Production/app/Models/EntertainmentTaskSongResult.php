<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Hrd\Models\Employee;

// use Modules\Production\Database\Factories\EntertainmentTaskSongResultFactory;

class EntertainmentTaskSongResult extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'nas_path',
        'employee_id',
        'note',
    ];

    // protected static function newFactory(): EntertainmentTaskSongResultFactory
    // {
    //     // return EntertainmentTaskSongResultFactory::new();
    // }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskSong::class, 'task_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(EntertainmentTaskSongResultImage::class, 'result_id');
    }
}
