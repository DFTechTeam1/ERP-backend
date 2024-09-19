<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class OfficialEmailList implements FromArray
{
    public function array(): array
    {
        $employees = \Modules\Hrd\Models\Employee::selectRaw('name,id')
            ->whereRaw("status != " . \App\Enums\Employee\Status::Inactive->value)
            ->get();

        $output = [];

        $permissions = [
            'administrators',
            'http',
            'Tim Animasi',
            'Tim Asset',
            'users'
        ];

        foreach ($employees as $employee) {

            $exp = explode(' ', $employee->name);
            $firstname = $exp[0];
            $lastname = array_pop($exp);
            $fullname = $firstname . $lastname;
            $emailFormat = $fullname . '@dfactory.pro';
            $password = generateRandomSymbol() . generateRandomPassword() . generateRandomSymbol();

            $output[] = [strtolower($fullname), $password, '', strtolower($emailFormat), ''];
        }

        return $output;
    }
}
