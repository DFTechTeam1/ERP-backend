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
            'clearCache'
        ];

        $bar = $this->output->createProgressBar(count($todos));

        $bar->start();

        foreach ($todos as $key => $todo) {
            $this->{$todo}();

            if ($key == 0) {
                $bar->setMessage('Running migration and seed default data ...');
            } else if ($key == 1) {
                $bar->setMessage('Prepare all application configuration ...');
            } else if ($key == 2) {
                $bar->setMessage('Create user for each employee ...');
            } else if ($key == 3) {
                $bar->setMessage('Delete all cache ...');
            }

            $bar->advance();
        }

        $bar->finish();

        Schema::enableForeignKeyConstraints();

    }

    protected function clearCache()
    {
        Artisan::call('optimize:clear');

        Artisan::call('config:clear');

        Artisan::call('cache:clear');
    }

    protected function migration()
    {
        Artisan::call('migrate:refresh --seed');

        // running countries
        \Illuminate\Support\Facades\Schema::dropIfExists('countries');
        \Illuminate\Support\Facades\Schema::dropIfExists('cities');
        \Illuminate\Support\Facades\Schema::dropIfExists('states');

        \Illuminate\Support\Facades\DB::unprepared(file_get_contents(database_path('countries.sql')));
    }

    protected function caching()
    {
        cachingSetting();
    }

    protected function addEmployeeAsUser()
    {
        $this->assignEmployeeAsUser();
    }

    protected function assignEmployeeAsUser()
    {
        $employees = \Modules\Hrd\Models\Employee::where('status', '!=', \App\Enums\Employee\Status::Inactive->value)
            ->get();

        \App\Models\User::where('email', '!=', 'admin@admin.com')
            ->delete();

        $directorPosition = json_decode(getSettingByKey('position_as_directors'), true);
        $directorPosition = collect($directorPosition)->map(function ($item) {
            return getIdFromUid($item, new \Modules\Company\Models\Position());
        })->toArray();

        $pmPosition = json_decode(getSettingByKey('position_as_project_manager'), true);
        $pmPosition = collect($pmPosition)->map(function ($item) {
            return getIdFromUid($item, new \Modules\Company\Models\Position());
        })->toArray();

        $marketingPosition = getSettingByKey('position_as_marketing');
        $marketingPosition = getIdFromUid($marketingPosition, new \Modules\Company\Models\Position());

        $projectManagerRole = Role::findByName('project manager');
        $productionRole = Role::findByName('production');
        $directorRole = Role::findByName('director');
        $marketingRole = Role::findByName('marketing');
        $suRole = Role::findByName('root');

        foreach ($employees as $employee) {
            $payload = [
                'employee_id' => $employee->id,
                'email' => $employee->email,
                'email_verified_at' => date('Y-m-d H:i:s'),
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ];

            if (in_array($employee->position_id, $directorPosition)) {
                $payload['is_director'] = true;
                $payload['role'] = 'director';
            } else if (in_array($employee->position_id, $pmPosition)) {
                $payload['is_project_manager'] = true;
                $payload['role'] = 'project manager';
            } else if ($employee->position_id == $marketingPosition) {
                $payload['is_employee'] = true;
                $payload['role'] = 'marketing';
            } else {
                $payload['is_employee'] = true;
                $payload['role'] = 'production';
            }

            $user = \App\Models\User::create(collect($payload)->except(['role'])->toArray());

            if ($payload['role'] == 'production') {
                $user->assignRole($productionRole);
            } else if ($payload['role'] == 'project manager') {
                $user->assignRole($projectManagerRole);
            } else if ($payload['role'] == 'director') {
                $user->assignRole($directorRole);
            } else if ($payload['role'] == 'marketing') {
                $user->assignRole($marketingRole);
            }
        }
    }
}
