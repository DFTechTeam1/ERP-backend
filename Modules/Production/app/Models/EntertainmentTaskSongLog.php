<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

// use Modules\Production\Database\Factories\EntertainmentTaskSongLogFactory;

class EntertainmentTaskSongLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_song_list_id',
        'entertainment_task_song_id',
        'project_id',
        'text',
        'param_text',
        'employee_id'
    ];

    protected $appends = ['formatted_text'];

    // protected static function newFactory(): EntertainmentTaskSongLogFactory
    // {
    //     // return EntertainmentTaskSongLogFactory::new();
    // }

    public function formattedText(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['text']) && isset($this->attributes['param_text'])) {
            $param = $this->attributes['param_text'];

            if (!empty($param)) {
                $out = __($this->attributes['text'], gettype($param) == 'string' ? json_decode($param, true) : $param);
            } else {
                $out = __($this->attributes['text']);
            }
       } 

        return Attribute::make(
            get: fn() => $out
        );
    }

    public function paramText(): Attribute
    {
        return Attribute::make(
            get: fn(string $value) => $value ? json_decode($value, true) : NULL,
            set: fn(array $value) => !empty($value) ? json_encode($value) : NULL
        );
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(ProjectSongList::class, 'project_song_list_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTaskSong::class, 'entertainment_task_song_id');
    }
}
