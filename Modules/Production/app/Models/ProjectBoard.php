<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Production\Database\Factories\ProjectBoardFactory;

class ProjectBoard extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'name',
        'sort',
        'based_board_id',
    ];

    protected static function newFactory(): ProjectBoardFactory
    {
        return ProjectBoardFactory::new();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'project_board_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
