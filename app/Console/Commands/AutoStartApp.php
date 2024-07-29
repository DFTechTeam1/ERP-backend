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

    protected function permissionSeeder()
    {
        Schema::disableForeignKeyConstraints();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        DB::table('permissions')->truncate();

        $data = [
            ['name' => 'dashboard_access', 'group' => 'dashboard'],
            ['name' => 'create_user', 'group' => 'user_management'],
            ['name' => 'list_user', 'group' => 'user_management'],
            ['name' => 'edit_user', 'group' => 'user_management'],
            ['name' => 'detail_user', 'group' => 'user_management'],
            ['name' => 'delete_user', 'group' => 'user_management'],
            ['name' => 'create_role', 'group' => 'user_management'],
            ['name' => 'edit_role', 'group' => 'user_management'],
            ['name' => 'list_role', 'group' => 'user_management'],
            ['name' => 'delete_role', 'group' => 'user_management'],
            ['name' => 'create_employee', 'group' => 'employee'],
            ['name' => 'edit_employee', 'group' => 'employee'],
            ['name' => 'detail_employee', 'group' => 'employee'],
            ['name' => 'delete_employee', 'group' => 'employee'],
            ['name' => 'list_employee', 'group' => 'employee'],
            ['name' => 'invite_employee_as_user', 'group' => 'employee'],
            ['name' => 'import_employee', 'group' => 'employee'],
            ['name' => 'import_employee', 'group' => 'employee'],
            ['name' => 'list_division', 'group' => 'master'],
            ['name' => 'create_division', 'group' => 'master'],
            ['name' => 'edit_division', 'group' => 'master'],
            ['name' => 'delete_division', 'group' => 'master'],
            ['name' => 'list_position', 'group' => 'master'],
            ['name' => 'create_position', 'group' => 'master'],
            ['name' => 'edit_position', 'group' => 'master'],
            ['name' => 'delete_position', 'group' => 'master'],
            ['name' => 'list_supplier', 'group' => 'inventories'],
            ['name' => 'create_supplier', 'group' => 'inventories'],
            ['name' => 'edit_supplier', 'group' => 'inventories'],
            ['name' => 'delete_supplier', 'group' => 'inventories'],
            ['name' => 'list_brand', 'group' => 'inventories'],
            ['name' => 'create_brand', 'group' => 'inventories'],
            ['name' => 'edit_brand', 'group' => 'inventories'],
            ['name' => 'delete_brand', 'group' => 'inventories'],
            ['name' => 'list_unit', 'group' => 'inventories'],
            ['name' => 'create_unit', 'group' => 'inventories'],
            ['name' => 'edit_unit', 'group' => 'inventories'],
            ['name' => 'delete_unit', 'group' => 'inventories'],
            ['name' => 'list_inventory_type', 'group' => 'inventories'],
            ['name' => 'create_inventory_type', 'group' => 'inventories'],
            ['name' => 'edit_inventory_type', 'group' => 'inventories'],
            ['name' => 'delete_inventory_type', 'group' => 'inventories'],
            ['name' => 'list_inventory', 'group' => 'inventories'],
            ['name' => 'create_inventory', 'group' => 'inventories'],
            ['name' => 'edit_inventory', 'group' => 'inventories'],
            ['name' => 'delete_inventory', 'group' => 'inventories'],
            ['name' => 'import_inventory', 'group' => 'inventories'],
            ['name' => 'request_inventory', 'group' => 'inventories'],
            ['name' => 'request_inventory_event', 'group' => 'inventories'],
            ['name' => 'create_service_inventory', 'group' => 'inventories'],
            ['name' => 'reject_request_equipment', 'group' => 'inventories'],
            ['name' => 'accept_request_equipment', 'group' => 'inventories'],
            ['name' => 'list_addon', 'group' => 'addon'],
            ['name' => 'create_addon', 'group' => 'addon'],
            ['name' => 'update_addon', 'group' => 'addon'],
            ['name' => 'list_setting', 'group' => 'setting'],
            ['name' => 'setting_addon', 'group' => 'setting'],
            ['name' => 'setting_general', 'group' => 'setting'],
            ['name' => 'setting_kanban', 'group' => 'setting'],
            ['name' => 'list_project', 'group' => 'production'],
            ['name' => 'create_project', 'group' => 'production'],
            ['name' => 'edit_project', 'group' => 'production'],
            ['name' => 'delete_project', 'group' => 'production'],
            ['name' => 'add_team_member', 'group' => 'production'],
            ['name' => 'list_member', 'group' => 'production'],
            ['name' => 'move_task', 'group' => 'production'],
            ['name' => 'cancel_equipment', 'group' => 'production'],
            ['name' => 'cancel_equipment', 'group' => 'production'],
            ['name' => 'reject_request_equipment', 'group' => 'inventories'],
            ['name' => 'accept_request_equipment', 'group' => 'inventories'],
            ['name' => 'list_request_equipment', 'group' => 'inventories'],
            ['name' => 'move_task_to_progress', 'group' => 'production'],
            ['name' => 'move_task_to_review_pm', 'group' => 'production'],
            ['name' => 'move_task_to_review_client', 'group' => 'production'],
            ['name' => 'move_task_to_revise', 'group' => 'production'],
            ['name' => 'move_task_to_completed', 'group' => 'production'],
            ['name' => 'add_task', 'group' => 'production'],
            ['name' => 'list_task', 'group' => 'production'],
            ['name' => 'delete_task', 'group' => 'production'],
            ['name' => 'add_task_deadline', 'group' => 'production'],
            ['name' => 'add_task_attachment', 'group' => 'production'],
            ['name' => 'task_log_access', 'group' => 'production'],
            ['name' => 'proof_of_work_list', 'group' => 'production'],
            ['name' => 'change_project_status', 'group' => 'production'],
            ['name' => 'list_team_transfer', 'group' => 'production'],
        ];

        foreach ($data as $d) {
            DB::table('permissions')->where('name', $d['name'])->delete();

            $d['guard_name'] = 'sanctum';

            Permission::create($d);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::enableForeignKeyConstraints();
    }

    protected function roleSeeder()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            [
                'name' => 'root',
            ],
            [
                'name' => 'marketing',
            ],
            [
                'name' => 'project manager',
            ],
            [
                'name' => 'production',
            ],
            [
                'name' => 'it support',
            ],
            [
                'name' => 'director',
            ],
        ];

        foreach ($roles as $role) {
            try {
                DB::table('roles')->where('name', $role['name'])->delete();
                
            } catch (\Throwable $th) {}

            $roleData = Role::create(['name' => $role['name'], 'guard_name' => 'sanctum']);

            if ($role['name'] == 'root') {
                $permissions = Permission::all();
                foreach ($permissions as $permission) {
                    $roleData->givePermissionTo($permission);
                }
            }

        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function assignEmployeeAsUser()
    {
        $employees = \Modules\Hrd\Models\Employee::where('status', '!=', \App\Enums\Employee\Status::Inactive->value)
            ->get();

        \App\MOdels\User::where('email', '!=', 'admin@admin.com')
            ->delete();

        $this->permissionSeeder();

        $this->roleSeeder();

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
