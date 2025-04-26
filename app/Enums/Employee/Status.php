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

    public static function generateLabel(int $value)
    {
        return match ($value) {
            static::Deleted->value => __('global.deleted'),
            static::Permanent->value => __('global.permanent'),
            static::Contract->value => __('global.contract'),
            static::PartTime->value => __('global.partTime'),
            static::Freelance->value => __('global.freelance'),
            static::Internship->value => __('global.internship'),
            static::Inactive->value => __('global.inactive'),
            static::WaitingHR->value => __('global.waitingHr'),
            static::Probation->value => __('global.probation'),
        };
    }

    public static function generateChartColor(int $value)
    {
        return match ($value) {
            static::Deleted->value => generateRandomColor(__('global.deleted')),
            static::Permanent->value => '#009bde',
            static::Contract->value => '#f96d01',
            static::PartTime->value => generateRandomColor(__('global.partTime')),
            static::Freelance->value => generateRandomColor(__('global.freelance')),
            static::Internship->value => generateRandomColor(__('global.internship')),
            static::Inactive->value => generateRandomColor(__('global.inactive')),
            static::WaitingHR->value => generateRandomColor(__('global.waitingHr')),
            static::Probation->value => '#5b37d4',
        };
    }

    public static function getLabel(int $value)
    {
        switch ($value) {
            case static::Permanent->value:
                $value = __('global.permanent');
                break;

            case static::Contract->value;
                $value = __('global.contract');
                break;

            case static::PartTime->value:
                $value = __('global.partTime');
                break;

            case static::Freelance->value:
                $value = __('global.freelance');
                break;

            case static::Internship->value:
                $value = __('global.internship');
                break;

            case static::Inactive->value:
                $value = __('global.inactive');
                break;

            case static::Probation->value:
                $value = __('global.probation');
                break;

            default:
                $value = '-';
                break;
        }

        return $value;
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

    public static function getStatusForReport()
    {
        return [
            static::Permanent->value,
            static::Contract->value,
            static::PartTime->value,
            static::Probation->value
        ];
    }
}
