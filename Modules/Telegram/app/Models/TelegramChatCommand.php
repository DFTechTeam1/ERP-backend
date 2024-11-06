<?php

namespace Modules\Telegram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Telegram\Database\Factories\TelegramChatCommandFactory;

class TelegramChatCommand extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'chat_id',
        'command',
        'status',
        'current_function'
    ];

    protected static function newFactory(): TelegramChatCommandFactory
    {
        //return TelegramChatCommandFactory::new();
    }
}
