<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class MassResetPassword implements FromArray
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function array(): array
    {
        if (count($this->data) > 0) {
            $output = [];

            foreach ($this->data as $employee) {
                $output[] = [$employee['email'], $employee['password']];
            }
        }

        return $output;
    }
}
