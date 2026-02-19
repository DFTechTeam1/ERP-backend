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
