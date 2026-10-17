<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class BaseCurrencyNotFound extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Base currency is not set';

        return parent::__construct($message, $code, $previous);
    }
}
