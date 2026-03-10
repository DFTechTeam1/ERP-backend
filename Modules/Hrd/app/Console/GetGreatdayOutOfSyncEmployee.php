<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Services\GreatdayService;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class GetGreatdayOutOfSyncEmployee extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:out-of-sync-employee';

    /**
     * The console command description.
     */
    protected $description = 'Get Greatday out of sync employees.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $keepProcess = true;
        $limit = 100;
        $page = 1;

        while ($keepProcess) {
            $this->info("Start syncing for round {$page}");

            $getEmployees = $this->getEmployees($limit, $page);

            if ($getEmployees['isContinue']) {
                $process = $this->processingData($getEmployees, $page);

                if (! $process) {
                    $keepProcess = false;
                    $this->info('Process stopped due to error or data not found');
                    $this->info('');
                    $this->info('End syncing with round ' . $page);
                    return 1;
                }

                $page++;
            } else {
                $this->error('No more data to process');
                $keepProcess = false;
            }
        }
    }

    /**
     * Process the data and store it in out_of_sync_employees table if the employee is not exist in local database but exist in Greatday API.
     *
     * @param array $getEmployees
     * @param integer $page
     * @return boolean
     */
    protected function processingData(array $getEmployees, int $page): bool
    {
        $terminalStatus = EmploymentStatus::select("id")
            ->where('is_terminal', true)
            ->first();

        if (! $terminalStatus) {
            $this->error('Terminal status not found');
            return false;
        }

        $localEmployees = \Modules\Hrd\Models\Employee::selectRaw('id,employee_id,greatday_emp_id')
            ->whereNotIn('employment_status_id', [$terminalStatus->id])
            ->whereNotNull('greatday_emp_id')
            ->get();
        $localGreatdayEmpId = $localEmployees->pluck('greatday_emp_id')->toArray();

        $greatdayEmployees = $getEmployees['data'];

        $greatdayEmployeesResult = collect($greatdayEmployees->json()['data'])->pluck('empId')->toArray();

        // Get the different between localGreatdayEmpId and greatdayEmployeesResult
        // If there missing value in localGreatdayEmpId that exist in greatdayEmployeesResult, then it means that employee is out of sync
        // Then get detail of employee from greatday and store it in out_of_sync_employees table
        $outOfSyncEmpIds = array_diff($greatdayEmployeesResult, $localGreatdayEmpId);

        $this->info('Processing data ...');
        $progress = $this->output->createProgressBar(count($outOfSyncEmpIds));
        $progress->start();
        
        foreach ($outOfSyncEmpIds as $empId) {
            $employeeData = collect($greatdayEmployees->json()['data'])
                ->where('empId', $empId)->first();

            $selectedEmail = $employeeData['email'];
            
            if (\Illuminate\Support\Str::contains($selectedEmail, 'resign')) {
                continue;
            }

            // Check email on database
            $checkEmployee = \Modules\Hrd\Models\Employee::where('email', $selectedEmail)->first();

            if (! $checkEmployee) {
                \Modules\Hrd\Models\OutOfSyncEmployee::updateOrCreate(
                    [
                        'greatday_employee_id' => $employeeData['empId']
                    ],
                    [
                        'first_name' => $employeeData['firstName'],
                        'middle_name' => $employeeData['middleName'],
                        'last_name' => $employeeData['lastName'],
                        'email' => $employeeData['email'],
                        'employee_id' => $employeeData['empNo'],
                        'position_code' => $employeeData['posCode'],
                        'position_name' => $employeeData['posNameEn'],
                        'employment_status' => $employeeData['employmentStatus'],
                        'employment_status_code' => $employeeData['employmentStatusCode'],
                        'start_working_date' => isset($employeeData['startDate']) ? now()->parse($employeeData['startDate']) : null,
                        'end_working_date' => isset($employeeData['endDate']) ? now()->parse($employeeData['endDate']) : null,
                        'company_id' => $employeeData['companyId'],
                        'address' => $employeeData['address'],
                        'phone' => $employeeData['phone'],
                        'job_status' => $employeeData['jobStatus'],
                        'work_location_code' => $employeeData['worklocationCode'],
                        'cost_center_code' => $employeeData['costCode'],
                        'org_unit' => $employeeData['orgUnit'],
                        'employment_start_date' => isset($employeeData['employmentStartDate']) ? now()->parse($employeeData['employmentStartDate']) : null,
                        'status' => \App\Enums\Employee\OutOfSyncStatus::OutOfSync
                    ]
                );
            }

            $progress->advance();
        }

        $progress->finish();
        $this->info('Processing data for round ' . $page . ' completed.');

        return true;
    }

    /**
     * Get employees data from Greatday API with pagination.
     *
     * @param integer $limit
     * @param integer $page
     * @return array
     */
    protected function getEmployees(int $limit, int $page): array
    {
        $greatdayService = app(GreatdayService::class);
        $token = $greatdayService->login();

        // Get list of employees from Greatday API
        $greatdayEmployees = Http::withToken($token)
            ->post($greatdayService->getBaseUrl() . '/employees', [
                'page' => $page,
                'limit' => $limit
            ]);

        if ($greatdayEmployees->status() < 400 && isset($greatdayEmployees->json()['data'])) {
            if (count($greatdayEmployees->json()['data']) == 0) {
                $isContinue = false;
                goto output;
            }

            $isContinue = true;
            goto output;
        }

        $isContinue = false;

        output:
        return [
            'isContinue' => $isContinue,
            'data' => $greatdayEmployees
        ];
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }
}
