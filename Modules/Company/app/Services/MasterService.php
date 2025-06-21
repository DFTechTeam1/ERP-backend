<?php

namespace Modules\Company\Services;

use App\Enums\Employee\BloodType;
use App\Enums\Employee\BpjsKesehatanConfiguration;
use App\Enums\Employee\EmployeeTaxStatus;
use App\Enums\Employee\Gender;
use App\Enums\Employee\JhtConfiguration;
use App\Enums\Employee\JpConfiguration;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\OvertimeStatus;
use App\Enums\Employee\PtkpStatus;
use App\Enums\Employee\RelationFamily;
use App\Enums\Employee\Religion;
use App\Enums\Employee\SalaryConfiguration;
use App\Enums\Employee\SalaryType;
use App\Enums\Employee\TaxConfiguration;
use Modules\Company\Repository\BankRepository;
use Modules\Company\Repository\JobLevelRepository;

class MasterService
{
    private $jobLevelRepo;

    private $bankRepo;

    public function __construct(
        JobLevelRepository $jobLevelRepo,
        BankRepository $bankRepo
    ) {
        $this->jobLevelRepo = $jobLevelRepo;

        $this->bankRepo = $bankRepo;
    }

    /**
     * Get all religions from enums
     */
    public function getReligions(): array
    {
        $religions = Religion::cases();

        $religions = collect($religions)->map(function ($item) {
            return [
                'title' => Religion::getReligion($item->value),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $religions
        );
    }

    /*
     * Get tax configuration
     *
     * @return array
     */
    public function getTaxConfiguration(): array
    {
        $taxConfig = TaxConfiguration::cases();

        $taxConfig = collect($taxConfig)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $taxConfig
        );
    }

    /*
     * Get employee tax status
     *
     * @return array
     */
    public function getEmployeeTaxStatus(): array
    {
        $employeeTaxStatus = EmployeeTaxStatus::cases();

        $employeeTaxStatus = collect($employeeTaxStatus)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $employeeTaxStatus
        );
    }

    /*
     * Get jht configuration
     *
     * @return array
     */
    public function getJhtConfiguration(): array
    {
        $jhtConfig = JhtConfiguration::cases();

        $jhtConfig = collect($jhtConfig)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $jhtConfig
        );
    }

    /*
     * Get overtime status
     *
     * @return array
     */
    public function getOvertimeStatus(): array
    {
        $overtimeStatus = OvertimeStatus::cases();

        $overtimeStatus = collect($overtimeStatus)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $overtimeStatus
        );
    }

    /*
     * Get BPJS Kesehatan configuration
     *
     * @return array
     */
    public function getBpjsKesehatanConfig(): array
    {
        $bpjsKesehatanConfig = BpjsKesehatanConfiguration::cases();

        $bpjsKesehatanConfig = collect($bpjsKesehatanConfig)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $bpjsKesehatanConfig
        );
    }

    /*
     * Get JP Configuration
     *
     * @return array
     */
    public function getJpConfiguration(): array
    {
        $jpConfig = JpConfiguration::cases();

        $jpConfig = collect($jpConfig)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $jpConfig
        );
    }

    /**
     * Get All Configuration
     */
    public function getAllConfiguration(): array
    {
        $taxConfig = $this->getTaxConfiguration()['data'];
        $employeeTaxStatus = $this->getEmployeeTaxStatus()['data'];
        $salaryConfig = $this->getSalaryConfiguration()['data'];
        $jhtConfiguration = $this->getJhtConfiguration()['data'];
        $jpConfig = $this->getJpConfiguration()['data'];
        $overtimeStatus = $this->getOvertimeStatus()['data'];
        $bpjsKesConfig = $this->getBpjsKesehatanConfig()['data'];

        return generalResponse(
            message: 'Success',
            data: [
                'tax_config' => $taxConfig,
                'employee_tax_status' => $employeeTaxStatus,
                'salary_config' => $salaryConfig,
                'jht_config' => $jhtConfiguration,
                'jp_config' => $jpConfig,
                'overtime_status' => $overtimeStatus,
                'bpjs_kes_config' => $bpjsKesConfig,
            ]
        );
    }

    /**
     * Get all genders from enums
     */
    public function getGenders(): array
    {
        $genders = Gender::cases();
        $genders = collect($genders)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $genders
        );
    }

    /**
     * Get all genders from enums
     */
    public function getBanks(): array
    {
        $banks = $this->bankRepo->list(
            select: 'id,name,bank_code'
        );

        return generalResponse(
            message: 'success',
            error: false,
            data: collect((object) $banks)->map(function ($bank) {
                return [
                    'title' => $bank->name,
                    'value' => $bank->bank_code,
                ];
            })->toArray()
        );
    }

    /**
     * Get all martal status from enums
     */
    public function getMartialStatus(): array
    {
        $martialStatus = MartialStatus::cases();
        $martialStatus = collect($martialStatus)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $martialStatus
        );
    }

    /**
     * Get all relation family from enums
     */
    public function getRelationFamily(): array
    {
        $relations = RelationFamily::cases();
        $relations = collect($relations)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $relations
        );
    }

    /**
     * Get all blood type from enums
     */
    public function getBloodType(): array
    {
        $bloodTypes = BloodType::cases();
        $bloodTypes = collect($bloodTypes)->map(function ($item) {
            return [
                'title' => $item->value,
                'value' => $item->value,
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
     */
    public function getLevelStaff(): array
    {
        $jobLevels = $this->jobLevelRepo->list(
            select: 'uid as value,name as title',
        )->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $jobLevels
        );
    }

    /**
     * Get all salary type from enums
     */
    public function getSalaryType(): array
    {
        $salaryTypes = SalaryType::cases();
        $salaryTypes = collect($salaryTypes)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $salaryTypes
        );
    }

    /**
     * Get all salary configuration types
     */
    public function getSalaryConfiguration(): array
    {
        $salaryConfig = SalaryConfiguration::cases();
        $salaryConfig = collect($salaryConfig)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $salaryConfig
        );

    }

    /**
     * Get all salary type from enums
     */
    public function getPtkpType(): array
    {
        $ptkpTypes = PtkpStatus::cases();
        $ptkpTypes = collect($ptkpTypes)->map(function ($item) {
            return [
                'title' => $item->label(),
                'value' => $item->value,
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $ptkpTypes
        );
    }
}
