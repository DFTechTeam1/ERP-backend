<?php

namespace Modules\Finance\Exceptions\Accounting;

use Exception;
use Throwable;

class OnlyOneLevelCostCenterParentAllowed extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $message = __('notification.onlyOneLevelCostCenterParentAllowed');

        return parent::__construct($message, $code, $previous);
    }
}
