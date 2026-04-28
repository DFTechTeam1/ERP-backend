<?php

namespace Modules\Email\Enums;

use Illuminate\Support\Facades\Validator;
use Modules\Email\Data\Employees\Resign\SlackEmployeeResignData;

enum SlackType: string
{
    case ResignSuccess = 'resign-employee-success';

    public function getSlackNotificationClass()
    {
        return match ($this) {
            self::ResignSuccess => 'ResignationNotification',
        };
    }

    public function getTypeData(array|object $payload): SlackEmployeeResignData
    {
        return match ($this) {
            self::ResignSuccess => SlackEmployeeResignData::from($payload),
        };
    }

    public function getTypeValidator()
    {
        return match ($this) {
            self::ResignSuccess => [
                'employeeName' => 'required',
                'employeeEmail' => 'required',
                'message' => 'required',
                'success' => 'required',
                'errorMessage' => 'nullable',
            ],
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
