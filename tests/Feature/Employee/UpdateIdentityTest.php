<?php

namespace Tests\Feature\Employee;

use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Modules\Hrd\Models\Employee;
use Tests\TestCase;

class UpdateIdentityTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication;

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
    public function testMissingPayloadOnUpdate(): void
    {
        $payload = [
            'address' => '',
            'id_number' => ''
        ];

        $response = $this->putJson(route('api.employees.updateIdentity', ['uid' => 'idnumber']), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('id_number', $response['errors']);

        parent::tearDown();
    }

    public function testCurrentAddressIsMissing(): void
    {
        $payload = [
            'address' => 'Jl. okeoke',
            'id_number' => '1231231',
            'is_residence_same' => 1,
            'current_address' => ''
        ];

        $response = $this->putJson(route('api.employees.updateIdentity', ['uid' => 'idnumber']), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('current_address', $response['errors']);

        parent::tearDown();
    }

    public function testUpdateIdentitySuccess(): void
    {
        $employees = Employee::factory()
            ->count(1)
            ->create();
        $employee = $employees[0];

        $payload = [
            'address' => $employee->address,
            'is_residence_same' => 1,
            'current_address' => 'my address',
            'id_number' => $employee->id_number
        ];

        $response = $this->putJson(route('api.employees.updateIdentity', ['uid' => $employee->uid]), $payload, [
            'Authorization' => $this->token
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', ['current_address' => 'my address']);

        parent::tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
