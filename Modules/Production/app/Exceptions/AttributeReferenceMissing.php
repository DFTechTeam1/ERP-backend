<?php

namespace Modules\Production\Exceptions;

class AttributeReferenceMissing
{
    private $message;

    private $errors;

    public function __construct(string $message, array $errors = [])
    {
        $this->message = $message;
        $this->errors = $errors;

        return response()->json(['errors'], 422);
    }
}
