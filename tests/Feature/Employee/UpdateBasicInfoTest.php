<?php

namespace Tests\Feature\Employee;

use App\Traits\HasEmployeeConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Hrd\Models\Employee;
use Tests\TestCase;

class UpdateBasicInfoTest extends TestCase
{
    use HasEmployeeConstructor, RefreshDatabase, TestUserAuthentication;

    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();

        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);

        $this->token = $this->getToken($userData['user']);
    }

    /**
     * A basic feature test example.
     */
    public function test_missing_payload_on_update_request(): void
    {
        $payload = [
            'name' => '',
            'email' => '',
        ];

        $response = $this->putJson(route('api.employees.updateBasicInfo', ['uid' => '123']), $payload, [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(422);

        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('name', $response['errors']);
        $this->assertArrayHasKey('email', $response['errors']);

        parent::tearDown();
    }

    public function test_email_already_taken(): void
    {
        $employees = Employee::factory()
            ->count(2)
            ->create();

        $payload = [
            'name' => $employees[0]->name,
            'email' => $employees[1]->email,
        ];

        $response = $this->putJson(route('api.employees.updateBasicInfo', ['uid' => $employees[0]->uid]), $payload, [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('email', $response['errors']);
        $this->assertStringContainsString('exists', $response['errors']['email'][0]);

        parent::tearDown();
    }

    public function test_update_basic_info_success(): void
    {
        $employees = Employee::factory()
            ->count(1)
            ->create();

        $employee = $employees[0];
        $payload = [
            'email' => $employee->email,
            'name' => 'updated name',
            'nickname' => $employee->nickname,
            'phone' => $employee->phone,
            'employee_id' => $employee->employee_id,
            'id_number' => $employee->id_number,
            'place_of_birth' => $employee->place_of_birth,
            'date_of_birth' => $employee->date_of_birth,
            'gender' => $employee->gender,
            'martial_status' => $employee->martial_status,
            'blood_type' => $employee->blood_type,
            'religion' => $employee->religion,
        ];

        $response = $this->putJson(route('api.employees.updateBasicInfo', ['uid' => $employee->uid]), $payload, [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', ['name' => 'updated name']);

        parent::tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
