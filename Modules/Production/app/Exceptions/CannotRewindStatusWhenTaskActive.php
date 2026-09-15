<?php

namespace Modules\Production\Exceptions;

use Exception;
use Throwable;

class CannotRewindStatusWhenTaskActive extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = "Cannot revert to distribute if task status is not waiting to approval";
        return parent::__construct($message, $code, $previous);
    }
}
