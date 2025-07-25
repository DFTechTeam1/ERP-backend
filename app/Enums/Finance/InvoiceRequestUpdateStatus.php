<?php

namespace App\Enums\Finance;

enum InvoiceRequestUpdateStatus: int {
    case Approved = 1;
    case Pending = 2;
    case Rejected = 3;
}