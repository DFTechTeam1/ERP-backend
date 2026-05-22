<?php

namespace Modules\Email\Data\Employees\User;

use Modules\Email\Data\BaseData;

final class InvitationEmail extends BaseData
{
    public function __construct(
        public string $employeeName,
        public string $email,
        public string $password,
        public ?string $erpUrl,
        public ?string $activationUrl
    ) {}
}
