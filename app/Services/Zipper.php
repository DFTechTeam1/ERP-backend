<?php

namespace App\Services;

use STS\ZipStream\Facades\Zip;

class Zipper
{
    public $zippy;

    public function __construct(string $filepath)
    {
        $this->zippy = new Zip($filepath);
    }
}
