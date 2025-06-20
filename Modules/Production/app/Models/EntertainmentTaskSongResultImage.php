<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\EntertainmentTaskSongResultImageFactory;

class EntertainmentTaskSongResultImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'result_id',
        'path',
    ];

    // protected static function newFactory(): EntertainmentTaskSongResultImageFactory
    // {
    //     // return EntertainmentTaskSongResultImageFactory::new();
    // }
}
