<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Hrd\Models\Employee;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MakeEmployeeAsSync extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:sync-employee';

    /**
     * The console command description.
     */
    protected $description = 'Sync data employee with talenta';

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
        Employee::where('deleted_at', NULL)
            ->where('is_sync_with_talenta', 0)
            ->update([
                'is_sync_with_talenta' => 1
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
