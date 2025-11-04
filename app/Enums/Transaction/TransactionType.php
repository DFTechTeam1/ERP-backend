<?php

namespace App\Enums\Transaction;

enum TransactionType: string
{
    case DownPayment = 'down_payment';
    case Credit = 'credit';
    case Repayment = 'repayment';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::DownPayment => 'Down Payment',
            self::Credit => 'Credit',
            self::Repayment => 'Repayment',
            self::Refund => 'Refund',
        };
    }

    public function categoryType(): string
    {
        return match ($this) {
            self::DownPayment => 'income',
            self::Credit => 'income',
            self::Repayment => 'income',
            self::Refund => 'expense',
        };
    }
}