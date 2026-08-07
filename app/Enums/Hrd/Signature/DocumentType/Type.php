<?php

namespace App\Enums\Hrd\Signature\DocumentType;

enum Type: string
{
    case Hr = 'hr';
    case Legal = 'legal';
    case Finance = 'finance';
    case Compliance = 'compliance';

    // label
    public function label(): string
    {
        return match ($this) {
            self::Hr => 'HR',
            self::Legal => 'Legal',
            self::Finance => 'Finance',
            self::Compliance => 'Compliance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Hr => 'blue',
            self::Legal => 'deep-purple',
            self::Finance => 'teal',
            self::Compliance => 'grey',
        };
    }
}
