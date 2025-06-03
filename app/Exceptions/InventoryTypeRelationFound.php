<?php

namespace App\Exceptions;

use Exception;

class InventoryTypeRelationFound extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = __('notification.inventoryTypeRelationFound');

        parent::__construct($message, $code, $previous);
    }
}
