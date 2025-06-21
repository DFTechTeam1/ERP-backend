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
        } elseif ($data == 'Inactive') {
            return self::Inactive;
        } elseif ($data == 'Part Time') {
            return self::PartTime;
        }
    }

    public function label()
    {
        return match ($this) {
            self::Deleted => __('global.deleted'),
            self::Permanent => __('global.permanent'),
            self::Contract => __('global.contract'),
            self::PartTime => __('global.partTime'),
            self::Freelance => __('global.freelance'),
            self::Internship => __('global.internship'),
            self::Inactive => __('global.inactive'),
            self::WaitingHR => __('global.waitingHr'),
            self::Probation => __('global.probation'),
        };
    }

    public static function generateLabel(int $value)
    {
        return match ($value) {
            self::Deleted->value => __('global.deleted'),
            self::Permanent->value => __('global.permanent'),
            self::Contract->value => __('global.contract'),
            self::PartTime->value => __('global.partTime'),
            self::Freelance->value => __('global.freelance'),
            self::Internship->value => __('global.internship'),
            self::Inactive->value => __('global.inactive'),
            self::WaitingHR->value => __('global.waitingHr'),
            self::Probation->value => __('global.probation'),
        };
    }

    public static function generateChartColor(int $value)
    {
        return match ($value) {
            self::Deleted->value => generateRandomColor(__('global.deleted')),
            self::Permanent->value => '#009bde',
            self::Contract->value => '#f96d01',
            self::PartTime->value => generateRandomColor(__('global.partTime')),
            self::Freelance->value => generateRandomColor(__('global.freelance')),
            self::Internship->value => generateRandomColor(__('global.internship')),
            self::Inactive->value => generateRandomColor(__('global.inactive')),
            self::WaitingHR->value => generateRandomColor(__('global.waitingHr')),
            self::Probation->value => '#5b37d4',
        };
    }

    public static function getLabel(int $value)
    {
        switch ($value) {
            case self::Permanent->value:
                $value = __('global.permanent');
                break;

            case self::Contract->value:
                $value = __('global.contract');
                break;

            case self::PartTime->value:
                $value = __('global.partTime');
                break;

            case self::Freelance->value:
                $value = __('global.freelance');
                break;

            case self::Internship->value:
                $value = __('global.internship');
                break;

            case self::Inactive->value:
                $value = __('global.inactive');
                break;

            case self::Probation->value:
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
            self::Deleted => 'danger',
            self::Permanent => 'success',
            self::Contract => 'primary',
            self::PartTime => 'indigo-lighten-3',
            self::Freelance => 'cyan-lighten-4',
            self::Internship => 'light-green-lighten-3',
            self::Inactive => 'blue-grey-lighten-2',
            self::WaitingHR => 'warning',
            self::Probation => 'brown-lighten-2',
        };
    }

    public static function getStatusForReport()
    {
        return [
            self::Permanent->value,
            self::Contract->value,
            self::PartTime->value,
            self::Probation->value,
        ];
    }
}
