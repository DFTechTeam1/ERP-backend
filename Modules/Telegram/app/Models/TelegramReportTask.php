<?php

namespace Modules\Telegram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Telegram\Database\Factories\TelegramReportTaskFactory;

class TelegramReportTask extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'telegram_chat_id',
        'nas_link',
        'file_id',
        'mime_type'
    ];

    protected static function newFactory(): TelegramReportTaskFactory
    {
        //return TelegramReportTaskFactory::new();
    }
}
