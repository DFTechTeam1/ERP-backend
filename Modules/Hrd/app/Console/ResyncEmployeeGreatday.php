<?php

namespace Modules\Hrd\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Models\PositionBackup;
use Modules\Company\Models\Setting;
use Modules\Hrd\Data\Resign\ResignData;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Services\EmployeeService;
use Modules\Hrd\Services\GreatdayService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ResyncEmployeeGreatday extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:resync-employee-greatday';

    /**
     * The console command description.
     */
    protected $description = 'Resync employee IDs, positions, and master HRIS data from Greatday; updates email mappings, marks resigned employees, adjusts division/position settings, and clears cache.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Add personal_email column in employees table if not exists.
     * This column will be used to store the old email before we update the email with user email, because in greatday,
     * the email is used to identify the employee,
     * so we need to make sure that the email in our system is the same as the email in greatday before we sync the employee data from greatday.
     *
     * @return void
     */
    protected function addPersonalEmailColumn()
    {
        $this->info('Check personal_email column in employees table ...');

        // Check if personal_email exists or not
        if (Schema::hasColumn('employees', 'personal_email')) {
            $this->info('personal_email column already exists in employees table, skipping adding column.');

            return false;
        }

        // Create if not exists
        $this->info('Adding personal_email column in employees table ...');
        Schema::table('employees', function (Blueprint $table) {
            $table->string('personal_email')->nullable()->after('email');
        });
        $this->info('personal_email column added successfully.');

        return true;
    }

    /**
     * Update employee email with user email and store the old email in personal_email column.
     * This is needed because in greatday, the email is used to identify the employee,
     * so we need to make sure that the email in our system is the same as the email in greatday before we sync the employee data from greatday.
     *
     * @return void
     */
    protected function updateEmployeeEmailFromUserTable()
    {
        $this->info('Updating employee email from user table ...');

        $isColumnAdded = $this->addPersonalEmailColumn();

        // Stop the process if personal email already exists.
        // That's mean the email has been synchronized before
        if (! $isColumnAdded) {
            $this->info('Skipping updating employee email from user table because personal_email column already exists, to avoid overwriting existing personal_email data.');

            return;
        }

        $employees = Employee::select('id', 'email', 'user_id')
            ->with([
                'user:id,employee_id,email',
            ])
            ->get();

        $progress = $this->output->createProgressBar($employees->count());
        $progress->start();
        foreach ($employees as $employee) {
            if ($employee->user) {
                $userEmail = $employee->user->email;
                $currentEmail = $employee->email;

                // Update employee email with user email and store the old email in personal_email column
                Employee::where('id', $employee->id)
                    ->update([
                        'personal_email' => $currentEmail,
                        'email' => $userEmail,
                    ]);
            }

            $progress->advance();
        }

        $progress->finish();
        $this->info('');
        $this->info('Employee email updated successfully.');
        $this->info('');
    }

    /**
     * The function handles syncing employee and position data from Greatday to the system, updating
     * employee positions, seeding master HRIS data, and adjusting various positions and settings.
     *
     * @return The `handle()` function returns an integer value of 0 if the process completes
     *             successfully.
     */
    public function handle()
    {
        // Update employee email with user email and store the old email in personal_email column.
        // This is needed because in greatday, the email is used to identify the employee,
        // so we need to make sure that the email in our system is the same as the email in greatday before we sync the employee data from greatday.
        $this->updateEmployeeEmailFromUserTable();

        $service = app(GreatdayService::class);

        $accessToken = $service->login();

        $this->info('Fetching employee data from Greatday...');

        $response = Http::withToken($accessToken)->post($service->getBaseUrl().'/employees', [
            'page' => 1,
            'limit' => 100,
        ]);

        if ($response->status() < 300) {
            $total = $response->json()['total'] ?? 0;

            $progress = $this->output->createProgressBar($total);

            foreach ($response->json()['data'] as $employee) {
                $email = $employee['email'];

                Employee::where('email', $employee['email'])
                    ->update([
                        'greatday_emp_id' => $employee['empId'],
                    ]);

                $progress->advance();

                // Update resign employee
                if (Str::contains(strtolower($email), 'resign')) {
                    $this->updateResignEmployee($employee);
                }
            }

            $progress->finish();

            $this->info("\n{$total} Employee data resynced successfully.");

            // Positions
            $positions = Http::withToken($accessToken)->post($service->getBaseUrl().'/company/position', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($positions->status() < 300) {
                Artisan::call('app:resync-greatday-position');

                $this->info("\nStart update employee positions ...");

                $progress = $this->output->createProgressBar($total);

                foreach ($response->json()['data'] as $employee) {
                    $positionName = $employee['posNameEn'] ?? null;

                    if ($positionName) {
                        $position = PositionBackup::where('name', $positionName)->first();

                        if ($position) {
                            $updateEmployeePayload = [
                                'position_id' => $position->id,
                            ];

                            $user = User::selectRaw('id,employee_id,email')
                                ->where('email', $employee['email'])
                                ->first();

                            if ($user) {
                                $this->info("Updating employee {$user->email} with position {$positionName}");

                                Employee::where('id', $user->employee_id)
                                    ->update($updateEmployeePayload);
                            }
                        }
                    }
                    $progress->advance();
                }

                $progress->finish();

                $this->info("\nEmployee positions updated successfully.");
            }

            // Seed master hris data from greatday
            $this->seedGreatdayMasterData();

            // Adjust direktur division
            $this->adjustDivisionPosition();

            // Adjust production position in settings
            $this->adjustProductionPositionInSettings();

            // Adjust modeller position in settings
            $this->adjustModellerInSetting();

            // Adjust visual jokey position in settings
            $this->adjustVjPositionInSetting();

            // Clear cache
            Cache::flush();
            $this->info('Cache cleared successfully.');

            return 0;
        }

        $this->error('Failed to fetch employee data from Greatday. Status: '.$response->status());
        $this->error('Response: '.$response->body());
    }

    /**
     * Adjust modeller position in settings. This is needed because in greatday, modeller position is under production division,
     * but in our system we want to put modeller position in special production position in settings,
     * so we need to adjust the modeller position in settings after we sync the positions from greatday.
     */
    protected function adjustModellerInSetting(): void
    {
        $modellerPosition = PositionBackup::where('name', 'modeller')->first();

        if ($modellerPosition) {
            Setting::updateOrCreate(
                ['key' => 'special_production_position'],
                ['value' => $modellerPosition->uid]
            );

            $this->info('Modeller position updated in settings successfully.');
        }
    }

    /**
     * Adjust visual jokey positions in settings based on the positions in the entertainment division.
     */
    protected function adjustVjPositionInSetting(): void
    {
        $entertainmentDivision = DivisionBackup::where('name', 'entertainment')->first();
        $positions = PositionBackup::where('division_id', $entertainmentDivision->id)->pluck('uid')->toArray();

        // Assign to 'position_as_visual_jokey' key
        if ($positions) {
            Setting::updateOrCreate(
                ['key' => 'position_as_visual_jokey'],
                ['value' => json_encode($positions)]
            );

            $this->info('Visual jokey positions updated in settings successfully.');
        }
    }

    /**
     * Update resign employee. This function will be called when the employee status in greatday contains 'resign'.
     * The resign date will be set to 2025-05-01 and the reason will be set to 'Resigned sync'. The severance will be set to 0.
     */
    protected function updateResignEmployee(array $employee): void
    {
        $service = app(EmployeeService::class);

        $employeeData = Employee::select('uid')
            ->where('greatday_emp_id', $employee['empId'])
            ->first();

        $update = $service->mainResignLogic(
            data: ResignData::from([
                'resign_reason_code' => 'Resigned sync',
                'resign_date' => '2025-05-01', // static
                'remark' => 'Resigned sync',
                'sync_greatday' => false, // originates from Greatday, do not push back
            ]),
            employeeUid: $employeeData->uid ?? ''
        );

        if (! $update['error']) {
            $this->info("\nEmployee with Greatday ID {$employee['empId']} marked as resigned successfully.");
        } else {
            $this->error("\nFailed to mark employee with Greatday ID {$employee['empId']} as resigned. Message: ".$update['message']);
        }
    }

    /**
     * Adjust division for specific position. This is needed because in greatday,
     * direktur position is under company division,
     * but in our system we want to put direktur position under manajemen division,
     * so we need to adjust the division for direktur position after we sync the positions from greatday.
     */
    protected function adjustDivisionPosition(): void
    {
        // Move 'direktur' position to manajemen division
        $direkturPosition = PositionBackup::where('name', 'direktur')->first();
        $manajemenDivision = DivisionBackup::where('name', 'manajemen')->first();
        if ($direkturPosition && $manajemenDivision) {
            $direkturPosition->division_id = $manajemenDivision->id;
            $direkturPosition->save();

            $this->info("Position 'direktur' moved to 'manajemen' division successfully.");
        } else {
            $this->error("Failed to move 'direktur' position. Position or division not found.");
        }
    }

    /**
     * Adjust production positions in settings based on the positions in the production division.
     * This will be used for checking production team in project teams.
     */
    protected function adjustProductionPositionInSettings(): void
    {
        $productionDivision = DivisionBackup::where('name', 'production')->first();
        $productDevelopmentDivision = DivisionBackup::where('name', 'product development')->first();

        // Get position uids in production division
        if ($productionDivision) {
            $productionPositions = PositionBackup::select('uid')->where('division_id', $productionDivision->id)->pluck('uid')->toArray();

            $productDevPosition = [];
            if ($productDevelopmentDivision) {
                $productDevPosition = PositionBackup::select('uid')->where('division_id', $productDevelopmentDivision->id)->pluck('uid')->toArray();
            }

            if ($productionPositions) {
                // Merge
                $productionPositions = array_merge($productionPositions, $productDevPosition);

                // Insert to settings with key 'position_as_production'
                Setting::updateOrCreate(
                    ['key' => 'position_as_production'],
                    ['value' => json_encode($productionPositions)]
                );

                $this->info('Production positions updated in settings successfully.');
            }
        }
    }

    /**
     * Seed master data from greatday, such as timezone, religion, cost center, job grade, employment status, work location, shift pattern, job status, and nationality
     *
     * @return void
     */
    protected function seedGreatdayMasterData()
    {
        Artisan::call('app:seed-greatday-master');
    }

    /**
     * Handle notification after seeding greatday master data. If there is an error, it will show error message, if not it will show success message.
     */
    protected function handleNotificationGreatdaySeedingData(array $response, string $type): void
    {
        if ($response['error']) {
            $this->error("Failed to seed greatday {$type} data. Message: ".$response['message']);
        } else {
            $this->info("Greatday {$type} data seeded successfully.");
        }
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
