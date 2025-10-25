<?php

namespace Modules\Company\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MigrateNewPointScheme extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:migrate-new-point-scheme';

    /**
     * The console command description.
     */
    protected $description = 'Fill new point scheme data into the database';

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
        $points = \Modules\Company\Models\ProjectClass::where('point_2_team', 0)
            ->where('point_3_team', 0)
            ->where('point_4_team', 0)
            ->where('point_5_team', 0)
            ->get();
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
