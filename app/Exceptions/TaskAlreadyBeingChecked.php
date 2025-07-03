<?php

namespace App\Exceptions;

use Exception;

class TaskAlreadyBeingChecked extends Exception
{
    public function __construct()
    {
        parent::__construct(message: __('notification.taskAlreadyBeingChecked'));
    }
}
