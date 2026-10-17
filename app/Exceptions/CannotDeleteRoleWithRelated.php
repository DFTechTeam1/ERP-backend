<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class CannotDeleteRoleWithRelated extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Cannot delete this role when have user using it';

        return parent::__construct($message, $code, $previous);
    }
}
