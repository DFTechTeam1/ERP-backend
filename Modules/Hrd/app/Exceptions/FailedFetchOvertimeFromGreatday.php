<?php

namespace Modules\Hrd\Exceptions;

use Exception;
use Throwable;
use Override;

class FailedFetchOvertimeFromGreatday extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = "Failed to fetch overtime data from greatday";
        return parent::__construct($message, $code, $previous);
    }
}
