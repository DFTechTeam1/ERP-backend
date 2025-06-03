<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Hrd\Services\EmployeeService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CheckEmployeeResign extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:check-resign-employee';

    /**
     * The console command description.
     */
    protected $description = 'Check employee who resign today, update the employee status.';

    private $service;

    /**
     * Create a new command instance.
     */
    public function __construct(EmployeeService $service)
    {
        parent::__construct();

        $this->service = $service;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->service->checkEmployeeWhoResignToday();
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
