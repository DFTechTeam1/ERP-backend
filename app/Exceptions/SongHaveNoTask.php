<?php

namespace App\Exceptions;

use Exception;

class SongHaveNoTask extends Exception
{
    public function __construct()
    {
        parent::__construct(
            message: __('notification.songHaveNoTask')
        );
    }
}
