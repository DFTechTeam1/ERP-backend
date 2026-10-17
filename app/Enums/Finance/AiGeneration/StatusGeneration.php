<?php

namespace App\Enums\Finance\AiGeneration;

enum StatusGeneration: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
}
