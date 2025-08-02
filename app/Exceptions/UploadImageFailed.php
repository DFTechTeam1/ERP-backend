<?php

namespace App\Exceptions;

use Exception;

class UploadImageFailed extends Exception
{
    public function __construct()
    {
        parent::__construct(
            message: __('notification.failedToUploadImage')
        );
    }
}
