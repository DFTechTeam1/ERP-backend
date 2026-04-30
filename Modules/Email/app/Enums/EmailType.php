<?php

namespace Modules\Email\Enums;

use Illuminate\Support\Facades\Validator;
use Modules\Email\Data\Employees\Mutation\EmployeeData;
use Modules\Email\Data\Employees\Mutation\SupervisorData;
use Modules\Email\Data\Employees\Resign\EmployeeResign;

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

    public function getTypeData(array|object $payload): EmployeeData|SupervisorData|EmployeeResign
    {
        return match ($this) {
            self::Employee => EmployeeData::from($payload),
            self::Supervisor => SupervisorData::from($payload),
            self::ResignEmployee => EmployeeResign::from($payload),
        };
    }

    public static function injectTypeValidator(string $type): array
    {
        return match ($type) {
            self::Employee->value => [
                'employeeName' => 'required',
                'oldPosition' => 'required',
                'newPosition' => 'required',
                'department' => 'required',
                'effectiveDate' => 'required',
            ],
            self::Supervisor->value => [
                'supervisorName' => 'required',
                'employeeName' => 'required',
                'oldPosition' => 'required',
                'newPosition' => 'required',
                'department' => 'required',
                'effectiveDate' => 'required',
            ],
            self::ResignEmployee->value => [
                'employeeName' => 'required',
                'employeeId' => 'required',
                'position' => 'required',
                'department' => 'required',
                'resignDate' => 'required',
            ],
            self::InviteToErp->value => [
                'employeeName' => 'required',
                'email' => 'required',
                'password' => 'required',
                'erpUrl' => 'required',
                'activationUrl' => 'required',
            ],
        };
    }

    public function getTypeValidator(): array
    {
        return match ($this) {
            self::Employee => [
                'employeeName' => 'required',
                'oldPosition' => 'required',
                'newPosition' => 'required',
                'department' => 'required',
                'effectiveDate' => 'required',
            ],
            self::Supervisor => [
                'supervisorName' => 'required',
                'employeeName' => 'required',
                'oldPosition' => 'required',
                'newPosition' => 'required',
                'department' => 'required',
                'effectiveDate' => 'required',
            ],
            self::ResignEmployee => [
                'employeeName' => 'required',
                'employeeId' => 'required',
                'position' => 'required',
                'department' => 'required',
                'resignDate' => 'required',
            ]
        };
    }

    public function validatePayload(array $payload): ?array
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
