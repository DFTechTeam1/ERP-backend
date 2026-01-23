<?php

namespace Modules\Production\Dto\Song;

use Spatie\LaravelData\Data;

class RemovePicNotificationDto extends Data
{
    public function __construct(
        public string $songName,
        public string $projectName,
        public string $projectUid,
        public string $employeeNickname,
        public int $userId
    ) {}
}