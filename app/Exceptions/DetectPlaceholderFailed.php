<?php

namespace App\Exceptions;

use Exception;

class DetectPlaceholderFailed extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = __('notification.failedToDetectPlaceholder');

        return parent::__construct($message, $code, $previous);
    }
}
