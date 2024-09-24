<?php

namespace App\Exceptions;

use Exception;

class DoNotHaveAppPermission extends Exception
{
    public function __construct(string $message = '')
    {
        parent::__construct(__('notification.doNotHaveAppPermission'));
    }
}
