<?php

namespace App\Exceptions;

use Exception;

class RoleHasRelation extends Exception
{
    public function __construct()
    {
        parent::__construct(message: __("notification.cannotDeleteRoleBcsRelation"));
    }
}
