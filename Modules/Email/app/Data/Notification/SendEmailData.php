<?php

namespace Modules\Email\Data\Notification;

use Modules\Email\Data\BaseData;
use Modules\Email\Enums\EmailType;

final class SendEmailData extends BaseData
{
    public function __construct(
        public string $recipientEmail,
        public EmailType $emailType,
        public ?string $supervisorName,
        public ?string $employeeName,
        public ?string $oldPosition,
        public ?string $newPosition,
        public ?string $department,
        public ?string $effectiveDate,
    ) {}
}
