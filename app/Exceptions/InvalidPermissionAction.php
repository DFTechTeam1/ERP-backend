<?php

namespace App\Exceptions;

use Exception;

class InvalidPermissionAction extends Exception
{
    public function __construct(string $message = "You don't have permission to take this action")
    {
        parent::__construct($message);
    }
}
