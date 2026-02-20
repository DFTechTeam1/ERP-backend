<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ResyncEmployeeGreatday extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:resync-employee-greatday';

    /**
     * The console command description.
     */
    protected $description = 'Resync employee data with Greatday.';

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
        $service = app(\Modules\Hrd\Services\GreatdayService::class);

        $accessToken = $service->login();

        $this->info('Fetching employee data from Greatday...');

        $response = \Illuminate\Support\Facades\Http::withToken($accessToken)->post($service->getBaseUrl() . '/employees', [
            'page' => 1,
            'limit' => 100,
        ]);
        
        if ($response->status() < 300) {
            $total = $response->json()['total'] ?? 0;

            $progress = $this->output->createProgressBar($total);

            foreach ($response->json()['data'] as $employee) {
                \Modules\Hrd\Models\Employee::where('employee_id', $employee['empNo'])
                    ->update([
                        'greatday_emp_id' => $employee['empId']
                    ]);

                $progress->advance();
            }

            $progress->finish();
            
            $this->info("\n{$total} Employee data resynced successfully.");

            // Positions
            $positions = \Illuminate\Support\Facades\Http::withToken($accessToken)->post($service->getBaseUrl() . '/company/position', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($response->status() < 300) {
                $this->info("Starting to sync divisions ...");

                $progress = $this->output->createProgressBar(count($positions->json()['data']));

                // Insert division first
                foreach ($positions->json()['data'] as $position) {
                    $parentPath = $position['parentPath'];
                    $explodePath = explode(',', $parentPath);

                    $isDivision = count($explodePath) == 2;

                    if ($isDivision) {
                        $currentDivision = \Modules\Company\Models\DivisionBackup::selectRaw('id')
                            ->where('name', $position['posNameEn'])
                            ->first();

                        if (! $currentDivision) {
                            $currentDivision = \Modules\Company\Models\DivisionBackup::create([
                                'name' => $position['posNameEn'],
                            ]);
                        }
                    }

                    $progress->advance();
                }

                $this->info("Starting to sync positions ...");

                // Then insert positions
                foreach ($positions->json()['data'] as $position) {
                    $parentPath = $position['parentPath'];
                    $explodePath = explode(',', $parentPath);

                    if (count($explodePath) == 3) {
                        $divisionId = $explodePath[2];
                        $divisionName = collect($positions->json()['data'])->where('positionId', $divisionId)->first()['posNameEn'] ?? null;
                        $division = \Modules\Company\Models\DivisionBackup::where('name', $divisionName)->first();

                        if ($division) {
                            \Modules\Company\Models\PositionBackup::updateOrCreate(
                                ['name' => $position['posNameEn']],
                                [
                                    'division_id' => $division->id,
                                ]
                            );
                        }
                    }

                    $progress->advance();
                }

                $progress->finish();

                $this->info("\n{$total} Position data resynced successfully.");

                $this->info("\nStart update employee positions ...");

                // $progress = $this->output->createProgressBar($total);

                // foreach ($response->json()['data'] as $employee) {
                //     $positionName = $employee['posNameEn'] ?? null;

                //     if ($positionName) {
                //         $position = \Modules\Company\Models\PositionBackup::where('name', $positionName)->first();

                //         if ($position) {
                //             $updateEmployeePayload = [
                //                 'position_id' => $position->id,
                //             ];
                            
                //             \Modules\Hrd\Models\Employee::where('employee_id', $employee['empNo'])
                //                 ->update($updateEmployeePayload);
                //         }
                //     }
                //     $progress->advance();
                // }

                // $progress->finish();

                $this->info("\nEmployee positions updated successfully.");
            }

            return 0;
        }

        $this->error('Failed to fetch employee data from Greatday. Status: ' . $response->status());
        $this->error('Response: ' . $response->body());
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
