<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentTaskSongItem extends Model
{
    use HasFactory;

    protected $table = 'entertainment_task_song_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entertainment_task_id',
        'song_item_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTask::class, 'entertainment_task_id');
    }

    public function songItem(): BelongsTo
    {
        return $this->belongsTo(ProjectSongItem::class, 'song_item_id');
    }
}
