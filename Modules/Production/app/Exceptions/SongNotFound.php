<?php

namespace Modules\Production\Exceptions;

use Exception;

class SongNotFound extends Exception
{
    public function __construct()
    {
        parent::__construct(
            message: __('notification.songNotFound'),
            code: 500
        );
    }
}
