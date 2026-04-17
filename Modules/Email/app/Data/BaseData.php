<?php

namespace Modules\Email\Data;

abstract class BaseData
{
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}