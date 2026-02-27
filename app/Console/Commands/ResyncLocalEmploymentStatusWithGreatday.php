<?php

namespace App\Console\Commands;

use App\Enums\Employee\Status;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Services\GreatdayService;

class ResyncLocalEmploymentStatusWithGreatday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:resync-local-employment-status-with-greatday';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resync greatday employment status with current enum in local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting resync of local employment status with Greatday...');

        DB::transaction(function () {
            $this->mainProcess();
            $this->migrateCurrentEmploymentStatus();
        });

        $this->info('Resync process completed successfully.');
        return 0; // Return 0 to indicate success
    }

    protected function mainProcess()
    {
        // Greatday employment status
        $greatdayService = app(GreatdayService::class);
        $accessToken = $greatdayService->login();

        if (!$accessToken) {
            $this->error('Failed to authenticate with Greatday API. Please check your credentials and try again.');
            return 1; // Return a non-zero code to indicate failure
        }

        $greatdayStatuses = Http::withToken($accessToken)->post($greatdayService->getBaseUrl() . '/company/employmentstatus', [
            'page' => 1,
            'limit' => 100
        ]);

        if ($greatdayStatuses->status() < 400) {
            $progress = $this->output->createProgressBar(count($greatdayStatuses->json()['data']));

            $payload = [];

            foreach ($greatdayStatuses->json()['data'] as $status) {

                $payload[] = [
                    'code' => $status['employmentstatusCode'],
                    'name' => $status['employmentstatusNameEn'],
                    'is_active' => true,
                    'is_terminal' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $progress->advance();
            }

            $progress->finish();
            $this->info(""); // Move to the next line after the progress bar

            EmploymentStatus::upsert(
                $payload,
                ['code'], // Unique key to determine if a record should be updated or inserted
                ['name', 'is_active', 'is_terminal', 'updated_at'] // Columns to update if the record already exists
            );

            $this->info('Greatday employment statuses have been synced successfully.');
        } else {
            $this->error('Failed to fetch employment statuses from Greatday API. Status code: ' . $greatdayStatuses->status());
            return 1; // Return a non-zero code to indicate failure
        }

        $this->info('Ensuring Resign status exists in local database...');

        // Add resign status manually
        EmploymentStatus::updateOrCreate(
            ['code' => 'RESIGN'],
            [
                'name' => 'Resign',
                'is_active' => true,
                'is_terminal' => true,
                'updated_at' => now(),
            ]
        );

        $this->info('Resign status has been ensured in local database.');
    }

    protected function migrateCurrentEmploymentStatus()
    {
        $this->info('Starting migration of current employee statuses to match Greatday employment statuses...');

        $employees = Employee::selectRaw('id,status')->get();

        $resignStatus = [
            Status::Deleted->value,
            Status::Inactive->value,
        ];

        $greatdayStatuses = EmploymentStatus::selectRaw('id,name')->get();

        $progress = $this->output->createProgressBar(count($employees));
        $resignStatusId = EmploymentStatus::select('id')->where('name', 'Resign')->first()?->id || 0;
        $permanentStatusId = EmploymentStatus::select('id')->where('name', 'Karyawan Tetap')->first()?->id || 0;
        $partimeStatusId = EmploymentStatus::select('id')->where('name', 'Karyawan Paruh Waktu')->first()?->id || 0;
        $contractStatusId = EmploymentStatus::select('id')->where('name', 'Kontrak Pertama')->first()?->id || 0;
        $internshipStatusId = EmploymentStatus::select('id')->where('name', 'Karyawan Magang')->first()?->id || 0;
        $probationStatusId = EmploymentStatus::select('id')->where('name', 'Percobaan')->first()?->id || 0;

        foreach ($employees as $employee) {
            $currentStatus = $employee->status->value;

            if (in_array($currentStatus, $resignStatus)) {
                $status = $resignStatusId;
            } else if ($currentStatus == Status::Permanent->value) {
                $status = $permanentStatusId;
            } else if ($currentStatus == Status::PartTime->value) {
                $status = $partimeStatusId;
            } else if ($currentStatus == Status::Contract->value) {
                $status = $contractStatusId;
            } else if ($currentStatus == Status::Internship->value) {
                $status = $internshipStatusId;
            } else if ($currentStatus == Status::Probation->value) {
                $status = $probationStatusId;
            } else {
                $status = 0; // Default to 0 if no matching status is found
            }

            $this->info("Updating employee ID {$employee->id} from {$currentStatus} to employment status ID {$status}...");

            $employee->update([
                'employment_status_id' => $status,
            ]);

            $progress->advance();
        }

        $progress->finish();

        $this->info(""); // Move to the next line after the progress bar
        $this->info('Employee statuses have been migrated successfully.');
    }
}
