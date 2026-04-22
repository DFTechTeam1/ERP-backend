<?php

namespace Modules\Email\Enums;

use Illuminate\Support\Facades\Validator;
use Modules\Email\Data\Employees\Mutation\EmployeeData;
use Modules\Email\Data\Employees\Mutation\SupervisorData;
use Modules\Email\Data\Notification\SendEmailData;

enum EmailType: string
{
    case Employee = 'employee';
    case Supervisor = 'supervisor';
    case ResignEmployee = 'resign-employee';
    case InviteToErp = 'invite-to-erp';

    public function getMailable(): string
    {
        return match ($this) {
            self::Employee => 'EmployeeMutationMail',
            self::Supervisor => 'SupervisorMutationMail',
            self::ResignEmployee => 'ResignEmployeeMail',
            self::InviteToErp => 'InviteToErpMail'
        };
    }

    public function getTypeData(SendEmailData $payload)
    {
        if ($this == self::Employee) {
            return new EmployeeData(
                employeeName: $payload->employeeName,
                oldPosition: $payload->oldPosition,
                newPosition: $payload->newPosition,
                department: $payload->department,
                effectiveDate: $payload->effectiveDate
            );
        } else {
            return new SupervisorData(
                supervisorName: $payload->supervisorName,
                employeeName: $payload->employeeName,
                oldPosition: $payload->oldPosition,
                newPosition: $payload->newPosition,
                department: $payload->department,
                effectiveDate: $payload->effectiveDate
            );
        }
    }

    public function getTypeValidator(): array
    {
        if ($this == self::Employee) {
            return [
                'employeeName' => 'required',
                'oldPosition' => 'required',
                'newPosition' => 'required',
                'department' => 'required',
                'effectiveDate' => 'required',
            ];
        } else {
            return [
                'supervisorName' => 'required',
                'employeeName' => 'required',
                'oldPosition' => 'required',
                'newPosition' => 'required',
                'department' => 'required',
                'effectiveDate' => 'required',
            ];
        }
    }

    public function validatePayload(SendEmailData $payload): ?array
    {
        // Get data per type
        $typeData = $this->getTypeData($payload);

        $validator = Validator::make($typeData->toArray(), $this->getTypeValidator());

        if ($validator->fails()) {
            return validationErrorResponse($validator->errors()->toArray());
        }

        return null;
    }
}
