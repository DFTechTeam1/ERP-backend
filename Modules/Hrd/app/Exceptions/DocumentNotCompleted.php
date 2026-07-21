<?php

namespace Modules\Hrd\Exceptions;

use Exception;
use Throwable;

class DocumentNotCompleted extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = $message ?: __('notification.documentNotCompleted');

        return parent::__construct($message, $code, $previous);
    }
}
