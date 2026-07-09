<?php

namespace Modules\Production\Exceptions;

use Exception;
use Throwable;
use Override;

class ProjectLeadAlreadyHaveRelation extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = __('notification.projectLeadAlreadyHaveRelation');
        return parent::__construct($message, $code, $previous);
    }
}
