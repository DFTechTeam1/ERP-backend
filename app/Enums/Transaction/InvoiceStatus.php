<?php

namespace App\Enums\Transaction;

enum InvoiceStatus: int {
    case Sent = 1;
    case Paid = 2;
    case Cancelled = 3;
}