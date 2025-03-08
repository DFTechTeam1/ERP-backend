<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PrepareEmployeeDataBeforeMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prepare-employee-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare all data related with employees table before do migration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Artisan::call('company:migrate-old-division'); // create new division and position table then seed with the correct value
        Artisan::call('module:seed Company --class=JobLevelSeeder'); // Running job level sedder
        Artisan::call('company:migrate-job-level'); // create job_levels table
        Artisan::call('hrd:sync-employee'); // make employee is_sync_with_talenta column as TRUE
    }
}
