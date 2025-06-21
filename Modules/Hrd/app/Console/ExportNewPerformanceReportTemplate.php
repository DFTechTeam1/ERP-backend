<?php

namespace Modules\Hrd\Console;

use App\Exports\NewTemplatePerformanceReportExport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ExportNewPerformanceReportTemplate extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:export-performance-report-new-template
                            {startDate : Start Date to export}
                            {endDate : End date to export}';

    /**
     * The console command description.
     */
    protected $description = 'Export all data in the given date';

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
        $this->info('start: '.$startDate);
        $filename = "performance_report_{$startDate}_until_{$endDate}.xlsx";

        // $export = Excel::store(new NewTemplatePerformanceReportExport('2025-02-23', '2025-03-22'), "hrd/performance_report/{$filename}", 'public');

        (new NewTemplatePerformanceReportExport($startDate, $endDate))->store('testing.xlsx', 'public');

        $this->info(asset("storage/hrd/performance_report/{$filename}"));
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
