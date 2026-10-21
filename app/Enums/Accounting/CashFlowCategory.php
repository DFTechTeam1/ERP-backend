<?php

namespace App\Enums\Accounting;

enum CashFlowCategory: string
{
    case Operating = 'operating';
    case Investing = 'investing';
    case Financing = 'financing';
}
