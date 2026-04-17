<?php

namespace Modules\Email\Services;

use App\Exceptions\NotFoundError;
use Modules\Email\Data\Notification\SendEmailData;
use Modules\Email\Jobs\SendEmailJob;
use Modules\Hrd\Repository\EmployeeRepository;

class EmailService {
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

    public function send(SendEmailData $payload): array
    {
        try {
            $isNotValid = $payload->emailType->validatePayload($payload);

            // Return validation error if exists
            if ($isNotValid) return $isNotValid;

            $employee = $this->employeeRepo->show(
                uid: 'id',
                select: 'id',
                where: "email = '{$payload->recipientEmail}'"
            );

            // Validate employee
            if (! $employee) {
                throw new NotFoundError(message: "Employee is not found");
            }

            SendEmailJob::dispatch($payload);

            return generalResponse(
                message: "Success",
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}