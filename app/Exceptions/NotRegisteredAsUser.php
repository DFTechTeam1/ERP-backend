<?php

namespace App\Exceptions;

use Exception;

class NotRegisteredAsUser extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = __('notification.notRegisteredAsUser');

        parent::__construct($message, $code, $previous);
    }
}
