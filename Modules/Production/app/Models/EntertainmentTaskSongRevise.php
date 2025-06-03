<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Production\Database\Factories\EntertainmentTaskSongReviseFactory;

class EntertainmentTaskSongRevise extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_song_list_id',
        'entertainment_task_song_id',
        'reason',
        'images',
    ];

    public function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? date('d F Y H:i', strtotime($value)) : '-'
        );
    }

    public function images(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ! empty($value) ? json_decode($value, true) : [],
            set: fn ($value) => ! empty($value) ? json_encode($value) : null
        );
    }

    // protected static function newFactory(): EntertainmentTaskSongReviseFactory
    // {
    //     // return EntertainmentTaskSongReviseFactory::new();
    // }

    public function song(): BelongsTo
    {
        return $this->belongsTo(ProjectSongList::class, 'project_song_list_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskSong::class, 'entertainment_task_song_id');
    }
}
