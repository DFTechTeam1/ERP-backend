<?php

namespace Tests\Feature\Employee;

use App\Traits\HasEmployeeConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Modules\Company\Database\Factories\ProvinceFactory;
use Modules\Company\Models\Branch;
use Modules\Company\Models\Position;
use Modules\Company\Models\Province;
use Modules\Hrd\Models\Employee;
use Tests\TestCase;

use function PHPSTORM_META\map;

class UpdateEmployeeTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication, HasEmployeeConstructor;

    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();
        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);

        $this->token = $this->getToken($userData['user']);

        ProvinceFactory::$sequence = 10;
    }

    /**
     * A basic feature test example.
     */
    public function testUpdateEmployeeReturnErrorMissingParam(): void
    {
        $payload = [
            'name' => ''
        ];

        $response = $this->putJson(route('api.employees.update', ['uid' => 1]), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('name', $response['errors']);
    }

    public function testUpdateEmployeeWithNonUniqueEmail(): void
    {
        $uniqueEmail = 'email@email.com';

        $employees = Employee::factory()->count(1)->create([
            'email' => $uniqueEmail
        ]);

        $updateEmployees = Employee::factory()->count(1)->create([
            'email' => 'update@email.com'
        ]);

        $payload = [
            'email' => $uniqueEmail
        ];

        $response = $this->putJson(route('api.employees.update', ['uid' => $updateEmployees[0]->uid]), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('email', $response['errors']);
        $this->assertStringContainsString('exists', $response['errors']['email'][0]);

        parent::tearDown();
    }

    public function testUpdateEmployeeSuccess(): void
    {
        $position = Position::factory()->count(1)->create();
        $branch = Branch::factory()->count(1)->create();

        $employees = Employee::factory()->count(1)->create([
            'level_staff' => 'manager',
            'join_date' => date('Y-m-d'),
            'position_id' => $position[0]->id,
            'branch_id' => $branch[0]->id
        ]);

        $payload = collect((object) $employees[0])->only([
            'name',
            'nickname',
            'email',
            'date_of_birth',
            'place_of_birth',
            'martial_status',
            'religion',
            'phone',
            'id_number',
            'address',
            'current_address',
            'is_residence_same',
            'position_id',
            'employee_id',
            'level_staff',
            'boss_id',
            'status',
            'branch_id',
            'join_date',
            'gender',
            'ptkp_status',
            'basic_salary',
            'salary_type',
        ])->toArray();
        $payload['name'] = 'Name updated';
        $payload['boss_id'] = '111';
        $payload['position_id'] = $position[0]->uid;

         $response = $this->putJson(route('api.employees.update', ['uid' => $employees[0]->uid]), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', ['name' => 'Name updated']);

        parent::tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
