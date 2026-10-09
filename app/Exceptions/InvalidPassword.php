<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InvalidPassword extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = "Password didn't match with our database";
        $code = 401;

        return parent::__construct($message, $code, $previous);
    }
}
