<?php

namespace Modules\Production\Models;

use App\Models\User;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectSongItem extends Model
{
    use HasFactory, ModelObserver;

    protected $table = 'project_song_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',
        'project_song_id',
        'song_name',
        'created_by',
    ];

    public function projectSong(): BelongsTo
    {
        return $this->belongsTo(ProjectSong::class, 'project_song_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entertainmentTaskSongItems(): HasMany
    {
        return $this->hasMany(EntertainmentTaskSongItem::class, 'song_item_id');
    }

    public function latestTask(): HasOne
    {
        return $this->hasOne(EntertainmentTaskSongItem::class, 'song_item_id')->latestOfMany();
    }
}
