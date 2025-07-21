<?php

namespace App\Enums\Finance;

enum InvoiceRequestUpdateStatus: int {
    case Pending = 2;
    case Approved = 1;
}