<?php

namespace Modules\Hrd\Exceptions;

use Exception;
use Throwable;

class UserNotHaveAccessToSign extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = __('notification.youDontHaveAccessToSignTheDocument');
        return parent::__construct($message, $code, $previous);
    }
}
