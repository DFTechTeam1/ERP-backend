<?php

namespace Modules\Email\Data\Notification;

use Modules\Email\Data\BaseData;

final class SlackSectionBlockData extends BaseData
{
    public function __construct(
        public string $message,
        public ?string $type
    ) {}
}
