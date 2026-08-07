<?php

namespace Modules\Finance\Exceptions;

use Exception;
use Throwable;

class InvoiceNotFound extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = __('notification.projectDealInvoiceNotFound');
        return parent::__construct($message, $code, $previous);
    }
}
