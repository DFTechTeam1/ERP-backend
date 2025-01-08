<?php

namespace Tests\Feature\Employee;

use App\Repository\RoleRepository;
use App\Repository\UserLoginHistoryRepository;
use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\RoleService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Modules\Hrd\Jobs\SendEmailActivationJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreNewUserTest extends TestCase
{
    use RefreshDatabase;

    private $service;

    private $token;

    private $roleServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $roleServiceMock = Mockery::mock(RoleService::class);

        $this->roleServiceMock = $this->instance(
            abstract: RoleService::class,
            instance: $roleServiceMock
        );

        $this->service = new UserService(
            new UserRepository,
            new EmployeeRepository,
            new RoleRepository,
            new UserLoginHistoryRepository,
            new GeneralService,
            new RoleService
        );
    }

    /**
     * A basic feature test example.
     */
    public function testStoreEmployeeAsUserIsSuccess(): void
    {
        Bus::fake();

        $role = Role::create(['name' => 'testing', 'guard_name' => 'sanctum']);

        $employees = Employee::factory()->count(1)->create();
        $employee = $employees[0];

        $payload = [
            'is_external_user' => 0,
            'email' => 'ilham@gmail.com',
            'employee_id' => $employee->id,
            'password' => 'password',
            'role_id' => $role->id
        ];

        $user = $this->service->mainServiceStoreUser($payload);

        Bus::assertDispatched(SendEmailActivationJob::class);

        $this->assertDatabaseHas('users', ['email' => 'ilham@gmail.com', 'employee_id' => $employee->id]);
        $this->assertTrue($user ? true : false);
        $this->assertTrue($user->hasRole('testing'));
    }
}
