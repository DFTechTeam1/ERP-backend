<?php

namespace Modules\Hrd\Services;

use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\Religion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Company\Repository\BranchRepository;
use Modules\Company\Repository\DivisionRepository;
use Modules\Company\Repository\JobLevelRepository;
use Modules\Company\Repository\PositionRepository;

class TalentaService {
    private $url;

    private $endpoint;

    private $token;

    private $dateRequest;

    private $requestMethod;

    private $urlParam = '';

    private $payload = [];

    /**
     * Set endpoint and main url
     *
     * @param string $type
     * @return void
     */
    public function setUrl(string $type)
    {
        $this->endpoint = config("talenta.endpoint_list.{$type}");
        $this->url = config('talenta.base_uri') . $this->endpoint;
        $this->requestMethod = config("talenta.endpoint_method.{$type}");
    }

    /**
     * Set url query parameters
     *
     * @param array $params
     * @return void
     */
    public function setUrlParams(array $params)
    {
        $this->payload = $params;
    }

    /**
     * Generate authorization token
     *
     * @return void
     */
    protected function generateHmac(): void
    {
        $datetime       = Carbon::now()->toRfc7231String();
        $request_line   = "GET {$this->endpoint} HTTP/1.1";
        $payload        = implode("\n", ["date: {$datetime}", $request_line]);
        $digest         = hash_hmac('sha256', $payload, config('talenta.client_secret'), true);
        $signature      = base64_encode($digest);

        $clientId       = config('talenta.client_id');
        $completeSecret = "hmac username=\"{$clientId}\", algorithm=\"hmac-sha256\", headers=\"date request-line\", signature=\"{$signature}\"";

        $this->token = $completeSecret;
        $this->dateRequest = $datetime;
    }

    /**
     * Make a request to talenta server
     *
     */
    public function makeRequest()
    {
        // generate secret token
        $this->generateHmac();

        $method = $this->requestMethod;

        // make a request
        $response = Http::withHeaders([
                'Authorization' => $this->token,
                'Date' => $this->dateRequest
            ])
            ->acceptJson()
            ->$method($this->url, $this->payload);

        return $response->json();
    }

    public function buildEmployeePayload(array $request): array
    {
        // TODO: Get current user. Who operate this function
        $user = auth()->user()->load('employee');
        $createdBy = $user->employee->talenta_user_id;

        //set name
        $expName = explode(' ', $request['name']);
        $lastName = array_pop($expName);
        $firstName = implode(' ', $expName);

        // get branch
        $branchRepo = new BranchRepository();
        $branch = $branchRepo->show(uid: $request['branch_id'], select: 'name');

        // get organization / division
        $divisionRepo = new DivisionRepository();
        $division = $divisionRepo->show(uid: $request['division_id'], select: 'name');

        // get position
        $positionRepo = new PositionRepository();
        $position = $positionRepo->show(uid: $request['position_uid'], select: 'name');

        // get job level
        $jobLevelRepo = new JobLevelRepository();
        $jobLevel = $jobLevelRepo->show(uid: $request['job_level_uid'], select: 'name');

        $payload = [
            'created_by' => $createdBy ?? 365229, // required,
            'employee_id' => $request['employee_id'],
            'first_name' => $firstName, // required
            'last_name' => $lastName,
            'email' => $request['email'], // required
            'date_of_birth' => $request['date_of_birth'], // required
            'gender' => $request['gender'] == 'male' ? 1 : 2, // required
            'marital_status' => $request['martial_status'] == MartialStatus::Single->value ? 1 : 2, // required
            'place_of_birth' => $request['place_of_birth'], // required
            'mobile_phone_number' => $request['phone'],
            'home_phone_number' => null,
            'blood_type' => empty($request['blood_type']) ? $request['blood_type'] : null,
            'religion' => Religion::generateTalentaVariable($request['religion']), // required
            'branch' => $branch->name, // required
            'organization_name' => $division->name, // required
            'job_position' => $position->name, // required
            'job_level' => $jobLevel->name, // required
            'employment_status' => 1, // required
            'join_date' => $request['join_date'], // required
            'end_employment_status_date' => '', // required if employment_status has end_date
            'schedule' => null,
            'barcode' => null,
            'citizen_id_type' => null,
            'citizen_id_expired_date' => null,
            'citizen_id' => null,
            'citizen_address' => null,
            'residential_address' => null,
            'postal_code' => null,
            'grade' => null,
            'class' => null,
            'approval_line' => null,
            'basic_salary' => $request['basic_salary'], // required
            'ptkp_status' => $request['ptkp_status'], // required
            'tax_configuration' => $request['tax_configuration'], // required
            'type_salary' => $request['salary_type'], // required
            'salary_configuration' => $request['salary_configuration'], // required
            'jht_configuration' => $request['jht_configuration'], // required
            'employee_tax_status' => $request['employee_tax_status'], // required
            'jp_configuration' => $request['jp_configuration'], // required
            'npp_bpjs_ketenagakerjaan' => "default",
            'overtime_status' => $request['overtime_status'], // required
            'bpjs_kesehatan_config' => $request['bpjs_kesehatan_config'], // required
            'payment_schedule' => null,
            'npwp' => null,
            'bank_name' => null,
            'bank_account' => null,
            'bank_account_holder' => null,
            'bpjs_ketenagakerjaan' => null,
            'bpjs_kesehatan' => null,
            'bpjs_kesehatan_family' => null,
            'taxable_date' => null,
            'overtime_working_day_default' => null,
            'overtime_day_off_default' => null,
            'overtime_national_holiday_default' => null,
            'beginning_netto' => null,
            'pph21_paid' => null,
            'bpjs_ketenagakerjaan_date' => null,
            'bpjs_kesehatan_date' => null,
            'jp_date' => null,
            'prorate_date' => null,
            'invite_ess' => 1, // required
        ];

        return $payload;
    }
}
