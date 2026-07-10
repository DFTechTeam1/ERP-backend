<?php

namespace Modules\Production\Exceptions;

use Exception;
use Throwable;

class ProjectLeadAlreadyCancel extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = __('notification.projectLeadAlreadyCancelled');
        return parent::__construct($message, $code, $previous);
    }
}
