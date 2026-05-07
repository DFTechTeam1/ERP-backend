<?php

namespace Modules\Email\Services;

use App\Exceptions\NotFoundError;
use Modules\Email\Data\BaseData;
use Modules\Email\Enums\EmailType;
use Modules\Email\Jobs\SendEmailJob;
use Modules\Hrd\Repository\EmployeeRepository;

class EmailService
{
    public function __construct(
        public EmployeeRepository $employeeRepo
    ) {}

    protected function formatClass(string $mailable)
    {
        return "\\Modules\\Email\\Emails\\{$mailable}";
    }

    protected function checkMailableClass(string $className): bool
    {
        return class_exists($className);
    }

    public function send(string $recipientEmail, EmailType $emailType, BaseData $payload): array
    {
        try {
            $employee = $this->employeeRepo->show(
                uid: 'id',
                select: 'id',
                where: "email = '{$recipientEmail}'"
            );

            // Validate employee
            if (! $employee && (request('validate') == null || request('validate') == '')) {
                throw new NotFoundError(message: 'Employee is not found');
            }

            SendEmailJob::dispatch(
                $recipientEmail,
                $emailType,
                $payload
            );

            return generalResponse(
                message: 'Success',
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
