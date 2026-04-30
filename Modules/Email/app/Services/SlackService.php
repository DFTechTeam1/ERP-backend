<?php

namespace Modules\Email\Services;

use Modules\Email\Data\BaseData;
use Modules\Email\Enums\SlackType;
use Modules\Email\Jobs\SendSlackJob;

class SlackService
{
    protected function formatClass(string $mailable)
    {
        return "\\Modules\\Email\\Emails\\{$mailable}";
    }

    protected function checkMailableClass(string $className): bool
    {
        return class_exists($className);
    }

    public function send(SlackType $slackType, BaseData $payload): array
    {
        try {
            // $employee = $this->employeeRepo->show(
            //     uid: 'id',
            //     select: 'id',
            //     where: "email = '{$recipientEmail}'"
            // );

            // // Validate employee
            // if (! $employee) {
            //     throw new NotFoundError(message: 'Employee is not found');
            // }

            SendSlackJob::dispatch(
                $slackType,
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
