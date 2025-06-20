<?php

namespace Modules\Production\Console;

use Illuminate\Console\Command;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Services\ProjectTaskService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class UpdateTaskIdentifier extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'task:update-task-identifier';

    /**
     * The console command description.
     */
    protected $description = 'Update all task identifier id on empty identifier data';

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
        $service = new ProjectTaskService(new ProjectTaskRepository);

        $service->massUpdateIdentifierID();
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
