<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ExchangeRateFetchFailed extends Exception
{
    public function __construct(string $errorType = 'unknown', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Failed to fetch exchange rates: {$errorType}", $code, $previous);
    }
}
