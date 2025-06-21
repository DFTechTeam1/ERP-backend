<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class UnitRelationFound extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = __('notification.unitRelationFound');

        parent::__construct($message, $code, $previous);
    }
}
