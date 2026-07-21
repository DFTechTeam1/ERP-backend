<?php

namespace App\Enums\Hrd\Signature;

enum SignatureTaskStatus: string
{
    case Signed = 'signed';
    case Waiting = 'waiting';
    case Locked = 'locked';
}
