<?php

namespace Modules\Production\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSong extends Model
{
    use HasFactory, ModelObserver;

    protected $table = 'project_songs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',
        'project_id',
        'group_name',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectSongItem::class, 'project_song_id');
    }
}
