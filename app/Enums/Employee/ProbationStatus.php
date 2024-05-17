<?php

namespace App\Enums\Employee;

enum ProbationStatus: string
{
    case Lulus = '1';
    case TidakLulus = '2';
    case Perpanjang = '3';
}
