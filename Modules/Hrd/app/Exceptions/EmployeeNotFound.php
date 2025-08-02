<?php

namespace Modules\Hrd\Exceptions;

use Exception;

class EmployeeNotFound extends Exception
{
    public function __construct()
    {
        parent::__construct(message: __('notification.employeeNotFound'));
    }
}
