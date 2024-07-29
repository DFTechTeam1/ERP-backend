<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use \PhpOffice\PhpSpreadsheet\Reader\Xlsx as Reader;
use Modules\Company\Models\Division;
use Modules\Company\Models\Position;
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

        $this->truncateProjectTabel();

        Artisan::call('app:truncate-employee');

        if ($this->positionSeeder()) {
            $service = new \Modules\Hrd\Services\EmployeeService;
    
            $data = $service->import(public_path('static_file/employee.xlsx'));
    
            if (!$data['error']) {
                $employees = $data['data'];
    
                $res = $service->submitImport($employees);
    
                if (!$res['error']) {
                    $this->assignEmployeeAsUser();
                }
            }
        }


        Schema::enableForeignKeyConstraints();

    }

    protected function registerVariables()
    {
        // director
        $directors = \Modules\Company\Models\Position::where('name', 'Head of Creative')
            ->orWhere('name', 'Lead Project Manager')
            ->get();
        $directors = collect($directors)->pluck('uid')->toArray();

        $settingService = new \Modules\Company\Services\SettingService;

        $settingService->storeVariables([
            'position_as_directors' => $directors
        ]);

        $pm = \Modules\Company\Models\Position::where('name', 'Project Manager')
            ->orWhere('name', 'Lead Project Manager')
            ->get();
        $pm = collect($pm)->pluck('uid')->toArray();

        $settingService->storeVariables([
            'position_as_project_manager' => $pm
        ]);

        $marketing = \Modules\Company\Models\Position::where('name', 'Lead Marcomm')
            ->first();
        $settingService->storeVariables([
            'position_as_marketing' => $marketing->uid
        ]);

        \Illuminate\Support\Facades\Cache::forget('setting');

        $setting = \Illuminate\Support\Facades\Cache::get('setting');
    
        if (!$setting) {
            \Illuminate\Support\Facades\Cache::rememberForever('setting', function () {
                $data = \Modules\Company\Models\Setting::get();

                return $data->toArray();
            });
        }
    }

    protected function assignEmployeeAsUser()
    {
        $employees = \Modules\Hrd\Models\Employee::where('status', '!=', \App\Enums\Employee\Status::Inactive->value)
            ->get();

        \App\MOdels\User::where('email', '!=', 'admin@admin.com')
            ->delete();

        $this->registerVariables();

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

    protected function truncateProjectTabel()
    {
        Schema::disableForeignKeyConstraints();
        if (\Illuminate\Support\Facades\Schema::hasTable('projects')) {
            \Modules\Production\Models\ProjectTaskWorktime::truncate();
            \Modules\Production\Models\ProjectTaskReviseHistory::truncate();
            \Modules\Production\Models\ProjectTaskProofOfWork::truncate();
            \Modules\Production\Models\ProjectTaskPic::truncate();
            \Modules\Production\Models\ProjectTaskPicLog::truncate();
            \Modules\Production\Models\ProjectTaskLog::truncate();
            \Modules\Production\Models\ProjectTaskAttachment::truncate();
            \Modules\Production\Models\ProjectReference::truncate();
            \Modules\Production\Models\ProjectPersonInCharge::truncate();
            \Modules\Production\Models\ProjectMarketing::truncate();
            \Modules\Production\Models\ProjectEquipment::truncate();
            \Modules\Production\Models\ProjectTask::truncate();
            \Modules\Production\Models\ProjectBoard::truncate();

            \Modules\Production\Models\Project::truncate();
        }
        Schema::enableForeignKeyConstraints();
    }

    protected function positionSeeder()
    {
        Schema::disableForeignKeyConstraints();
        Position::truncate();

        $hr = Division::findByName('hr');
        $finance = Division::findByName('finance');
        $it = Division::findByName('it');
        $marketing = Division::findByName('marketing');
        $production = Division::findByName('Production');
        $entertainment = Division::findByName('Entertainment');

        $reader = new Reader();
        
        $service = new \Modules\Hrd\Services\EmployeeService();
        $response = $service->readFile(public_path('static_file/employee.xlsx'));
        
        $positions = collect(array_values($response))->pluck('position_raw')->unique()->filter(function ($item) {
            return $item != null;
        })->values()->toArray();

        $out = [];
        foreach ($positions as $key => $position) {
            $position = ltrim(rtrim($position));

            $out[$key]['name'] = $position;

            if ($position == 'Admin Staff') {
                $out[$key]['division_id'] = $finance->id;
            } else if (
                $position == 'HR Officer' || 
                $position == 'HR & TA Admin'
            ) {
                $out[$key]['division_id'] = $hr->id;
            } else if (
                $position == 'IT Technical Support' ||
                $position == 'Full Stack Developer'
            ) {
                $out[$key]['division_id'] = $it->id;
            } else if (
                $position == 'Lead Marcomm' ||
                $position == 'Marketing Staff'
            ) {
                $out[$key]['division_id'] = $marketing->id;
            } else {
                $out[$key]['division_id'] = $production->id;
            }
        }

        $this->info(json_encode($out));

        foreach ($out as $o) {
            Position::create($o);
        }

        Schema::enableForeignKeyConstraints();

        return true;
    }
}
