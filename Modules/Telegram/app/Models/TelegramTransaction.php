<?php

namespace Modules\Telegram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Telegram\Database\Factories\TelegramTransactionFactory;

class TelegramTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'chat_id',
        'message_id',
        'identity',
        'status'
    ];

    protected static function newFactory(): TelegramTransactionFactory
    {
        //return TelegramTransactionFactory::new();
    }
}
