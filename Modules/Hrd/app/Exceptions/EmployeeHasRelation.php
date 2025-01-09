<?php

namespace Modules\Hrd\Exceptions;

use Exception;

class EmployeeHasRelation extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
