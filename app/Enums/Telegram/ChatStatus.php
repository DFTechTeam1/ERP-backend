<?php

namespace App\Enums\Telegram;

enum ChatStatus: int
{
    case Processing = 1;

    case Sent = 2;

    case Failed = 3;
}
