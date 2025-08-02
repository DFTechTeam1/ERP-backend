<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class GenerateQrcodeError extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = __('notification.qrcodeError');

        parent::__construct($message, $code, $previous);
    }
}
