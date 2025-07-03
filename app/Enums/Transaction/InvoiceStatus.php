<?php

namespace App\Enums\Transaction;

enum InvoiceStatus: int {
    case Unpaid = 1;
    case Paid = 2;
}