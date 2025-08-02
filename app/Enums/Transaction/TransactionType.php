<?php

namespace App\Enums\Transaction;

enum TransactionType: string {
    case DownPayment = 'down_payment';
    case Credit = 'credit';
    case Repayment = 'repayment';
}