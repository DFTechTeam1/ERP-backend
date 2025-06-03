<?php

namespace Modules\Telegram\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Telegram\Database\Factories\TelegramSessionFactory;

class TelegramSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'chat_id',
        'value',
        'status',
    ];

    // protected static function newFactory(): TelegramSessionFactory
    // {
    //     // return TelegramSessionFactory::new();
    // }

    public function scopeActive(Builder $query)
    {
        $query->where('status', true);
    }
}
