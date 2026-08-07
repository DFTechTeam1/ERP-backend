<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Hrd\Services\EmployeeService;

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

    public function __construct(protected EmployeeService $employeeService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * This used to carry its own copy of the fetch/diff/store logic, which drifted from the
     * service behind the "Refresh from Greatday" button — it still used firstOrCreate, so it
     * never refreshed an existing staging row. Both entry points now share one implementation.
     */
    public function handle(): int
    {
        $this->info('Syncing out of sync employees from Greatday ...');

        $result = $this->employeeService->getOutOfSyncEmployees();

        if ($result['error']) {
            $this->error($result['message'] ?? 'Failed to sync out of sync employees');

            return self::FAILURE;
        }

        $this->info('Staged out of sync: '.($result['data']['total_out_of_sync'] ?? 0));
        $this->info('Removed (gone from Greatday): '.($result['data']['total_removed'] ?? 0));
        $this->info('Done.');

        return self::SUCCESS;
    }
}
