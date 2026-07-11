<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class FailedToUploadFile extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = __("notification.failedToUploadFile");
        return parent::__construct($message, $code, $previous);
    }
}
