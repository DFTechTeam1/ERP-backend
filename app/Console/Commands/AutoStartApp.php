<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use \PhpOffice\PhpSpreadsheet\Reader\Xlsx as Reader;
use Modules\Company\Models\Division;
use Modules\Company\Models\Position;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AutoStartApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install-app';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        Schema::disableForeignKeyConstraints();

        $todos = [
            'migration',
            'caching',
            'addEmployeeAsUser',
        ];

        $bar = $this->output->createProgressBar(count($todos));

        $bar->start();

        foreach ($todos as $key => $todo) {
            if ($key == 0) {
                $bar->setMessage('Running migration and seed default data ...');
            } else if ($key == 1) {
                $bar->setMessage('Prepare all application configuration ...');
            } else if ($key == 2) {
                $bar->setMessage('Create user for each employee ...');
            }

            $this->{$todo}();

            $bar->advance();
        }

        $bar->finish();

        Schema::enableForeignKeyConstraints();

    }

    protected function migration()
    {
        Artisan::call('migrate:fresh --seed');
    }

    protected function caching()
    {
        cachingSetting();
    }

    protected function addEmployeeAsUser()
    {
        $this->assignEmployeeAsUser();
    }
}
