<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\GreatdayApiLogFactory;

class GreatdayApiLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'url',
        'creator',
        'response',
        'payload',
        'is_success'
    ];

    // protected static function newFactory(): GreatdayApiLogFactory
    // {
    //     // return GreatdayApiLogFactory::new();
    // }
}
