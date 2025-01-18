<?php

namespace App\Enums\Employee;

enum Status: int
{
    case Deleted = 0;
    case Permanent = 1;
    case Contract = 2;
    case PartTime = 3;
    case Freelance = 4;
    case Internship = 5;
    case Inactive = 6;
    case WaitingHR = 7;
    case Probation = 8;

    public static function generateStatus(string $data)
    {
        if ($data == 'Permanent') {
            return self::Permanent;
        } else if ($data == 'Inactive') {
            return self::Inactive;
        } else if ($data == 'Part Time') {
            return self::PartTime;
        }
    }

    public function label()
    {
        return match ($this) {
            static::Deleted => __('global.deleted'),
            static::Permanent => __('global.permanent'),
            static::Contract => __('global.contract'),
            static::PartTime => __('global.partTime'),
            static::Freelance => __('global.freelance'),
            static::Internship => __('global.internship'),
            static::Inactive => __('global.inactive'),
            static::WaitingHR => __('global.waitingHr'),
            static::Probation => __('global.probation'),
        };
    }

    public function statusColor()
    {
        return match ($this) {
            static::Deleted => 'danger',
            static::Permanent => 'success',
            static::Contract => 'primary',
            static::PartTime => 'indigo-lighten-3',
            static::Freelance => 'cyan-lighten-4',
            static::Internship => 'light-green-lighten-3',
            static::Inactive => 'blue-grey-lighten-2',
            static::WaitingHR => 'warning',
            static::Probation => 'brown-lighten-2',
        };
    }
}
