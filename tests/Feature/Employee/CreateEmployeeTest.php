<?php

namespace Tests\Feature\Employee;

use App\Enums\Employee\Gender;
use App\Enums\Employee\LevelStaff;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\PtkpStatus;
use App\Enums\Employee\Religion;
use App\Enums\Employee\SalaryType;
use App\Enums\Employee\Status;
use App\Models\User;
use App\Traits\HasEmployeeConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Modules\Company\Models\Branch;
use Modules\Company\Models\Position;
use Modules\Hrd\Jobs\SendEmailActivationJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Services\EmployeeService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateEmployeeTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication, HasEmployeeConstructor;

    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setConstructor();

        $userData = $this->auth();
        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);

        $this->token = $this->getToken($userData['user']);
    }

    /**
     * A basic feature test example.
     */
    public function testCreateEmployeeWithMissingParameter(): void
    {
        $payload = [];
        $response = $this->postJson(route('api.employees.store', $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]));

        $response->assertStatus(422);

        parent::tearDown();
    }

    public function testEmailAlreadyExists(): void
    {
        $uniqueEmail = 'ilham@gmail.com';
        $employees = Employee::factory()->count(1)->create([
            'email' => $uniqueEmail
        ]);

        $payload = [
            'email' => $uniqueEmail
        ];

        $response = $this->postJson(route('api.employees.store'), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
        $this->assertStringContainsString('The email has already been taken.', $response['errors']['email'][0]);

        parent::tearDown();
    }

    protected function payloadData()
    {
        $position = Position::factory()->count(1)->create();
        $branch = Branch::factory()->count(1)->create();
        $employeeBoss = Employee::factory()->count(1)->create();

        return [
            'name' => 'ilham',
            'nickname' => 'ilham',
            'email' => 'ilham@gmail.com',
            'date_of_birth' => '1996-05-24',
            'place_of_birth' => 'Surabay',
            'martial_status' => MartialStatus::Married->value,
            'religion' => Religion::Islam->value,
            'phone' => '088888888',
            'id_number' => '1223232323223333',
            'address' => 'Jl. oke',
            'current_address' => 'Jl. bos',
            'position_id' => $position[0]->uid,
            'employee_id' => 'DF001',
            'level_staff' => LevelStaff::Staff->value,
            'boss_id' => $employeeBoss[0]->uid,
            'status' => Status::Permanent->value,
            'branch_id' => $branch[0]->id,
            'join_date' => '2024-12-12',
            'gender' => Gender::Male->value,
            'ptkp_status' => PtkpStatus::K0->value,
            'basic_salary' => '15000000',
            'salary_type' => SalaryType::Monthly->value,
            'invite_to_talenta' => 0,
            'invite_to_erp' => 0
        ];
    }

    public function testSuccessCreateNewEmployeeWithoutCreateErpUser(): void
    {
        $payload = $this->payloadData();

        $response = $this->postJson(route('api.employees.store'), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);
        $response->assertStatus(201);
        
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);

        parent::tearDown();
    }

    public function testCreateEmployeeAndUserButMissingUserPayload(): void
    {
        $payload = $this->payloadData();
        $payload['invite_to_erp'] = 1;
        $payload['password'] = 'password';

        $response = $this->postJson(route('api.employees.store'), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
        $this->assertStringContainsString('required', $response['errors']['role_id'][0]);

        // employee should not be created
        $this->assertDatabaseMissing('employees', ['email' => $payload['email']]);

        parent::tearDown();
    }

    public function testSuccessCreateNewEmployeeWithCreateErpUser(): void
    {
        Bus::fake();

        $role = Role::create(['name' => 'testing', 'guard_name' => 'sanctum']);

        $payload = $this->payloadData();
        $payload['invite_to_erp'] = 1;
        $payload['password'] = 'password';
        $payload['role_id'] = $role->id;

        $response = $this->postJson(route('api.employees.store'), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('users', ['email' => $payload['email']]);

        $user = User::where('email', $payload['email'])->first();

        // check job is running
        Bus::assertDispatched(SendEmailActivationJob::class);
        
        // check role
        $this->assertTrue($user->hasRole('testing'));

        // check relation
        $this->assertNotNull($user->employee_id);
        $this->assertNotNull(Employee::where('user_id', $user->id)->first());

        parent::tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
