<?php

namespace Modules\Production\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

// use Modules\Production\Database\Factories\ProjectSongListFactory;

class ProjectSongList extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'project_id',
        'name',
        'is_request_edit',
        'is_request_delete',
        'created_by'
    ];

    // protected static function newFactory(): ProjectSongListFactory
    // {
    //     // return ProjectSongListFactory::new();
    // }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function task(): HasOne
    {
        return $this->hasOne(EntertainmentTaskSong::class, 'project_song_list_id');
    }
}
