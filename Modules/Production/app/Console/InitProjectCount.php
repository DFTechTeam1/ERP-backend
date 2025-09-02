<?php

namespace Modules\Production\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class InitProjectCount extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:init-project-count';

    /**
     * The console command description.
     */
    protected $description = 'Caching all projects';

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
        (new \App\Services\GeneralService)->generateDealIdentifierNumber();

        $this->info('Project count initialized successfully.');
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
