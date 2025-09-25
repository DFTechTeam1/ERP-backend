<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Production\Database\Factories\InteractiveProjectBoardFactory;

// use Modules\Production\Database\Factories\InteractiveProjectBoardFactory;

class InteractiveProjectBoard extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'name',
        'sort',
    ];

    protected static function newFactory(): InteractiveProjectBoardFactory
    {
        return InteractiveProjectBoardFactory::new();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(InteractiveProjectTask::class, 'intr_project_board_id', 'id');
    }
}
