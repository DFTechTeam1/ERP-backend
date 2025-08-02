<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Hrd\Services\PerformanceReportService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ManualExportPerformanceReport extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:export-performance-report
                            {startDate : Start date of report}
                            {endDate : End date of report}';

    /**
     * The console command description.
     */
    protected $description = 'Command description.';

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
        $startDate = $this->argument('startDate');
        $endDate = $this->argument('endDate');

        if ($startDate && $endDate) {
            $service = new PerformanceReportService;
            $service->importEmployeePoint([
                'employee_uids' => ['d0d9ffab-bf58-488b-87bb-a8c9c2fb2978'],
                'all_employee' => 1,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $this->info('You can download report in this link '.asset('point.xlsx'));
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
