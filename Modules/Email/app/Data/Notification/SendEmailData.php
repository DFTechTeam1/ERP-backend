<?php

namespace Modules\Email\Data\Notification;

use Modules\Email\Data\BaseData;
use Modules\Email\Enums\EmailType;
use Modules\Email\Http\Requests\SendEmailRequest;

final class SendEmailData extends BaseData {

    public function __construct(
        public string $recipientEmail,
        public EmailType $emailType,
        public string | null $supervisorName,
        public string | null $employeeName,
        public string | null $oldPosition,
        public string | null $newPosition,
        public string | null $department,
        public string | null $effectiveDate
    ) {}

    public static function fromRequest(SendEmailRequest $request): self
    {
        return new self(
            recipientEmail: $request->validated('recipientEmail'),
            emailType: EmailType::from($request->validated('emailType')),
            supervisorName: $request->validated('supervisorName'),
            employeeName: $request->validated('employeeName'),
            oldPosition: $request->validated('oldPosition'),
            newPosition: $request->validated('newPosition'),
            department: $request->validated('department'),
            effectiveDate: $request->validated('effectiveDate'),
        );
    }
}