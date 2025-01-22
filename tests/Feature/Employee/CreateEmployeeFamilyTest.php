<?php

namespace Tests\Feature\Employee;

use App\Enums\Employee\Gender;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\RelationFamily;
use App\Enums\Employee\Religion;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Modules\Hrd\Models\Employee;
use Tests\TestCase;

class CreateEmployeeFamilyTest extends TestCase
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
    public function testCreateWithMissingPayload(): void
    {
        $payload = [
            'name' => '',
            'relationship' => '',
            'gender' => '',
            'date_of_birth' => '',
        ];

        $response = $this->postJson(route('api.employees.storeFamily', ['employeeUid' => '123']), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);

        foreach ($payload as $key => $value) {
            $this->assertArrayHasKey($key, $response['errors']);
        }

        parent::tearDown();
    }

    public function testCreateFamilyIsSuccess(): void
    {
        $employees = Employee::factory()
            ->count(1)
            ->create();

        $employee = $employees[0];

        $payload = [
            'name' => 'required',
            'relationship' => RelationFamily::Father->value,
            'address' => 'address',
            'id_number' => '1231231231231231',
            'gender' => Gender::Male->value,
            'date_of_birth' => '1964-01-01',
            'religion' => Religion::Islam->value,
            'martial_status' => MartialStatus::Married->value,
            'job' => 'my job',
        ];

        $response = $this->postJson(route('api.employees.storeFamily', ['employeeUid' => $employee->uid]), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(201);

        parent::tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
