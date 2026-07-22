<?php

namespace Modules\Hrd\Exceptions;

use Exception;
use Throwable;

class PdfConversionFailed extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = $message ?: __('notification.pdfConversionFailed');

        return parent::__construct($message, $code, $previous);
    }
}
