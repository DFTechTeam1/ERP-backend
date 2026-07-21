<?php

namespace App\Enums\Hrd\Signature\Template;

enum DocumentFileStatus: int
{
    case Active = 1;
    case PendingReview = 2;
    case Rejected = 3;
    case Archived = 4;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::PendingReview => 'Pending Review',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived'
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::PendingReview => 'warning',
            self::Rejected => 'danger',
            self::Archived => 'grey'
        };
    }
}
