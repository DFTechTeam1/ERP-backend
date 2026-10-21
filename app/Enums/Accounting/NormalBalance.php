<?php

namespace App\Enums\Accounting;

enum NormalBalance: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
