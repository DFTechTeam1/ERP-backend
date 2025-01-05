<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repository\RoleRepository;
use App\Repository\UserLoginHistoryRepository;
use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\UserService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Modules\Company\Database\Factories\ProvinceFactory;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    private $service;

    private $userRepoMock;

    private $generalServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $userRepoMock = Mockery::mock(UserRepository::class);
        $generalServiceMock = Mockery::mock(GeneralService::class);

        $this->userRepoMock = $this->instance(
            abstract: UserRepository::class,
            instance: $userRepoMock
        );

        $this->generalServiceMock = $this->instance(
            abstract: GeneralService::class,
            instance: $generalServiceMock
        );

        $this->service = new UserService(
            $this->userRepoMock,
            new EmployeeRepository,
            new RoleRepository,
            new UserLoginHistoryRepository,
            $this->generalServiceMock
        );

        ProvinceFactory::$sequence = 1;
    }

    /**
     * A basic feature test example.
     */
    public function testUserNotFound(): void
    {
        // mock method
        $this->userRepoMock
            ->shouldReceive('detail')
            ->with(
                'id',
                '*',
                "email = 'email'",
                ['employee.position', 'roles']
            )
            ->andReturnNull();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('global.userNotFound'));

        $this->service->login(['email' => 'email', 'password' => 'pass']);
    }

    public function testUserIsNotActiveYet(): void
    {
        // mock method
        $this->userRepoMock
            ->shouldReceive('detail')
            ->once()
            ->with(
                'id',
                '*',
                "email = 'email'",
                ['employee.position', 'roles']
            )
            ->andReturn((object) [
                'email_verified_at' => false,
                'email' => 'email@email.com'
            ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('global.userNotActive'));

        $this->service->login(['email' => 'email', 'password' => 'pass']);
    }

    public function testPasswordWrong(): void
    {
        // mock method
        $this->userRepoMock
            ->shouldReceive('detail')
            ->once()
            ->with(
                'id',
                '*',
                "email = 'email'",
                ['employee.position', 'roles']
            )
            ->andReturn((object) [
                'email_verified_at' => true,
                'email' => 'email@email.com',
                'password' => Hash::make('testing')
            ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('global.credentialDoesNotMatch'));

        $this->service->login(['email' => 'email', 'password' => 'pass']);
    }

    public function testDoNotHavePermission(): void
    {
        // create user
        $users = User::factory()->count(1)->create();
        $user = $users[0];
        
        // mock method
        $this->userRepoMock
            ->shouldReceive('detail')
            ->once()
            ->with(
                'id',
                '*',
                "email = 'email'",
                ['employee.position', 'roles']
            )
            ->andReturn($user);
        
        // mock Hash facades
        Hash::shouldReceive('check')
            ->once()
            ->with('pass', $user->password)
            ->andReturnTrue();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('notification.doNotHaveAppPermission'));

        $this->service->login(['email' => 'email', 'password' => 'pass']);
    }

    protected function prepareDataForLogin()
    {
        // factory user
        $employee = Employee::factory()->count(1)->create();
        $users = User::factory()->count(1)->create([
            'employee_id' => $employee[0]->id,
            'password' => Hash::make('password')
        ]);
        $user = $users[0];

        $role = Role::create(['name' => 'root', 'guard_name' => 'sanctum']);
        $permission = Permission::create(['name' => 'all', 'guard_name' => 'sanctum']);
        $role->givePermissionTo($permission);

        // asign role to user
        $user->assignRole($role);

        // mock method
        $this->userRepoMock
            ->shouldReceive('detail')
            ->once()
            ->with(
                'id',
                '*',
                "email = '{$user->email}'",
                ['employee.position', 'roles']
            )
            ->andReturn($user);

        // mock Hash facades
        Hash::shouldReceive('check')
            ->once()
            ->with('password', $user->password)
            ->andReturnTrue();

        // mock general service
        // act like a root
        $this->generalServiceMock
            ->shouldReceive('getSettingByKey')
            ->atMost(2)
            ->with('position_as_directors')
            ->andReturnNull()
            ->shouldReceive('getSettingByKey')
            ->atMost(2)
            ->with('position_as_project_manager')
            ->andReturnNull()
            ->shouldReceive('getSettingByKey')
            ->atMost(2)
            ->with('super_user_role')
            ->andReturn($role->id)
            ->shouldReceive('getSettingByKey')
            ->once()
            ->with('app_name')
            ->andReturn('Testing')
            ->shouldReceive('getSettingByKey')
            ->once()
            ->with('board_start_calcualted')
            ->andReturn([])
            ->shouldReceive('getClientIp')
            ->atMost(2)
            ->andReturn('101')
            ->shouldReceive('getUserAgentInfo')
            ->atMost(2)
            ->andReturn('')
            ->shouldReceive('parseUserAgent')
            ->atMost(2)
            ->with('')
            ->andReturn(['browser' => ''])
            ->shouldReceive('formatNotifications')
            ->once()
            ->with([])
            ->andReturn([]);

        return $user;
    }

    public function testLoginWithoutRememberMe(): void
    {
        $user = $this->prepareDataForLogin();
                
        $response = $this->service->login(['email' => $user->email, 'password' => 'password']);
        
        $this->assertTrue(is_string($response));
    }

    public function testLoginWithRememberMe(): void
    {
        $user = $this->prepareDataForLogin();
                
        $response = $this->service->login(['email' => $user->email, 'password' => 'password', 'remember_me' => true]);
        
        $this->assertTrue(is_string($response));
    }
}
