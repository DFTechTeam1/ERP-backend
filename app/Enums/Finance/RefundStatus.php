<?php

namespace App\Enums\Finance;

enum RefundStatus: string
{
    case Pending = '1';
    case Paid = '2';

    public function label(): string
    {
        return match ($this) {
            RefundStatus::Pending => __('global.pending'),
            RefundStatus::Paid => __('global.paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            RefundStatus::Pending => 'warning',
            RefundStatus::Paid => 'primary',
        };
    }
}
