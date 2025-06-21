<?php

namespace Modules\Hrd\Console;

use App\Enums\Employee\Status;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeActiveReport;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class UpdateEmployeeActivePerMonth extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:update-active-employee';

    /**
     * The console command description.
     */
    protected $description = 'This command used to update current active employee each month';

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
        $employees = Employee::select('id')
            ->whereNotIn('status', [Status::Inactive->value, Status::Deleted->value])
            ->whereNull('end_date')
            ->count();

        $now = Carbon::now()->setTimezone(config('app.timezone'));

        EmployeeActiveReport::create([
            'month' => $now->format('m'),
            'year' => $now->format('Y'),
            'number_of_employee' => $employees,
        ]);
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
