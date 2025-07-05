<?php

namespace App\Enums\Transaction;

enum InvoiceStatus: int {
    case Unpaid = 1;
    case Paid = 2;

    public function label()
    {
        return match ($this) {
            self::Unpaid => __('global.unpaid'),
            self::Paid => __('global.paid'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::Unpaid => 'deep-orange-darken-2',
            self::Paid => 'green-lighten-2',
        };
    }
}