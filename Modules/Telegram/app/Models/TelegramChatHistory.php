<?php

namespace Modules\Telegram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Telegram\Database\Factories\TelegramChatHistoryFactory;

class TelegramChatHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'chat_id',
        'message',
        'message_id',
        'status',
        'chat_type',
        'bot_command',
        'from_customer',
        'is_closed'
    ];

    protected static function newFactory(): TelegramChatHistoryFactory
    {
        //return TelegramChatHistoryFactory::new();
    }
}
