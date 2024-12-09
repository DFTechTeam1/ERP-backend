<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\NasFolderCreationFactory;

class NasFolderCreation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_name',
        'folder_path',
        'project_id',
        'status',
        'type',
        'last_folder_name',
        'current_folder_name',
        'current_path'
    ];

    public function scopeActive(Builder $query)
    {
        $query->where('status', 1);
    }

    public function scopeProcess(Builder $query): void
    {
        $query->where('status', 2);
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('status', 0);
    }

    public function scopeByProject(Builder $query, $id): void
    {
        $query->where('project_id', $id);
    }
}
