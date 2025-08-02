<?php

namespace Modules\Company\Exceptions;

use Exception;

class BranchDeleteErrorRelation extends Exception
{
    public function __construct()
    {
        parent::__construct(message: __('notification.cannotDeleteBranchBcsRelation'));
    }
}
