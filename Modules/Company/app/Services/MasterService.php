<?php

namespace Modules\Company\Services;

use App\Enums\Employee\BloodType;
use App\Enums\Employee\Gender;
use App\Enums\Employee\LevelStaff;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\PtkpStatus;
use App\Enums\Employee\Religion;
use App\Enums\Employee\SalaryType;

class MasterService {
    /**
     * Get all religions from enums
     *
     * @return array
     */
    public function getReligions(): array
    {
        $religions = Religion::cases();

        $religions = collect($religions)->map(function ($item) {
            return [
                'title' => Religion::getReligion($item->value),
                'value' => $item->value
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $religions
        );
    }

    /**
     * Get all genders from enums
     *
     * @return array
     */
    public function getGenders(): array
    {
        $genders = Gender::cases();
        $genders = collect($genders)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $genders
        );
    }

    /**
     * Get all martal status from enums
     *
     * @return array
     */
    public function getMartialStatus(): array
    {
        $martialStatus = MartialStatus::cases();
        $martialStatus = collect($martialStatus)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $martialStatus
        );
    }

    /**
     * Get all blood type from enums
     *
     * @return array
     */
    public function getBloodType(): array
    {
        $bloodTypes = BloodType::cases();
        $bloodTypes = collect($bloodTypes)->map(function ($item) {
            return [
                'title' => $item->value,
                'value' => $item->value
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $bloodTypes
        );
    }

    /**
     * Get all level staff from enums
     *
     * @return array
     */
    public function getLevelStaff(): array
    {
        $bloodTypes = LevelStaff::cases();
        $bloodTypes = collect($bloodTypes)->map(function ($item) {
            return [
                'title' => LevelStaff::generateLabel($item->value),
                'value' => $item->value
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $bloodTypes
        );
    }

    /**
     * Get all salary type from enums
     *
     * @return array
     */
    public function getSalaryType(): array
    {
        $salaryTypes = SalaryType::cases();
        $salaryTypes = collect($salaryTypes)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $salaryTypes
        );
    }

    /**
     * Get all salary type from enums
     *
     * @return array
     */
    public function getPtkpType(): array
    {
        $ptkpTypes = PtkpStatus::cases();
        $ptkpTypes = collect($ptkpTypes)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $ptkpTypes
        );
    }
}