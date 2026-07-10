<?php

namespace Modules\Production\Exceptions;

use Exception;
use Throwable;

class ProjectLeadHaveBeenPast extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = __('notification.projectLeadHaveBeenPast');
        return parent::__construct($message, $code, $previous);
    }
}
