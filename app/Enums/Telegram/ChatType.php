<?php

namespace App\Enums\Telegram;

enum ChatType: string
{
    case FreeText = 'free_text';

    case BotCommand = 'bot_command';
}
