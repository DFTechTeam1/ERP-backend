<?php

namespace App\Enums\Production;

enum ProjectActivityItem: string
{
    case ChangeStatus = 'change_status';
    case ChangeProjectClass = 'change_project_class';

    public function title()
    {
        return match ($this) {
            self::ChangeStatus => __('global.projectActivityChangeStatusTitle'),
            self::ChangeProjectClass => __('global.projectActivityChangeProjectClassTitle'),
        };
    }

    public function description(?string $from = null, ?string $to = null)
    {
        $replace = ['from' => $from, 'to' => $to];

        return match ($this) {
            self::ChangeStatus => __('global.projectActivityChangeStatusDescription', $replace),
            self::ChangeProjectClass => __('global.projectActivityChangeProjectClassDescription', $replace),
        };
    }
}
