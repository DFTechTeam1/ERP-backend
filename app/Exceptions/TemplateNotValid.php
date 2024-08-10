<?php

namespace App\Exceptions;

use Exception;

class TemplateNotValid extends Exception
{
    public $message;

    public function __construct()
    {
        $this->message = __('global.templateNotValid');
    }
}
