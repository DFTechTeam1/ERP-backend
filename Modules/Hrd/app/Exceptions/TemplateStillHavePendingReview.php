<?php

namespace Modules\Hrd\Exceptions;

use Exception;
use Throwable;

class TemplateStillHavePendingReview extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $message = __("notification.templateStillHavePendingReviewDocument");
        return parent::__construct($message, $code, $previous);
    }
}
