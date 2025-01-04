<?php

namespace Modules\Production\Exceptions;

use Exception;

class ProjectNotFound extends Exception
{
    public function __construct()
    {
        parent::__construct(message: __('notification.projectNotFound'));
    }
}
