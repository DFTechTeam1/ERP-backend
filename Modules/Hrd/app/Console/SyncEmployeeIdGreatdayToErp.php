<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SyncEmployeeIdGreatdayToErp extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:sync-greatday-employee-id-to-erp';

    /**
     * The console command description.
     */
    protected $description = 'Make ERP employee ID the same with Greatday';

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

        $this->info('Logging in ......');
        $accessToken = $service->login();

        $this->info('Get employee data from greatday ...');
        $response = \Illuminate\Support\Facades\Http::withToken($accessToken)->post($service->getBaseUrl() . '/employees', [
            'page' => 1,
            'limit' => 100,
        ]);
        
        if ($response->status() < 300) {
            $this->info('Start synchronize data ...');
            $total = $response->json()['total'] ?? 0;

            $progress = $this->output->createProgressBar($total);

            foreach ($response->json()['data'] as $employee) {
                $email = $employee['email'];

                \Modules\Hrd\Models\Employee::where('email', $email)
                    ->update([
                        'employee_id' => $employee['empNo']
                    ]);

                $progress->advance();
            }

            $progress->finish();
            $this->info('');
            $this->info('Synchronize data is finished');
        } else {
            $this->info('Failed to fetch employee data from greatday');
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
