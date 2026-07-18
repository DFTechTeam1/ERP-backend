<?php

namespace Modules\Hrd\Exceptions;

use Exception;
use Throwable;

class UserAlreadySigned extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = __("notification.youAlreadySignedTheDocument");
        return parent::__construct($message, $code, $previous);
    }
}
