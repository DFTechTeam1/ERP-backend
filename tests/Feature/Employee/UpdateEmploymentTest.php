<?php

namespace Tests\Feature\Employee;

use App\Enums\Employee\LevelStaff;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Modules\Company\Models\Position;
use Modules\Hrd\Models\Employee;
use Tests\TestCase;

class UpdateEmploymentTest extends TestCase
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
    public function testUpdateEmploymentWithMissingParameter(): void
    {
        $payload = [
            'branch_id' => ''
        ];

        $response = $this->putJson(route('api.employees.updateEmployment', ['employeeUid' => '123']), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('branch_id', $response['errors']);

        parent::tearDown();
    }

    public function testBossIdShouldBeRequired(): void
    {
        $payload = [
            'level_staff' => LevelStaff::Staff->value,
            'boss_id' => ''
        ];
        
        $response = $this->putJson(route('api.employees.updateEmployment', ['employeeUid' => '123']), $payload , [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
        $this->assertStringContainsString(__('global.bossIdRequired'), $response['errors']['boss_id'][0]);

        parent::tearDown();
    }

    public function testUpdateEmployeeSuccess(): void
    {
        $employees = Employee::factory()->count(1)->create([
            'level_staff' => 'manager'
        ]);

        $positionData = Position::select('uid')
            ->where('id', $employees[0]->position_id)
            ->first();

        $positionUid = $positionData->uid;

        $payload = [
            'employee_id' => $employees[0]->employee_id,
            'branch_id' => $employees[0]->branch_id,
            'position_id' => $positionUid,
            'level_staff' => $employees[0]->level_staff,
            'status' => $employees[0]->status,
            'join_date' => $employees[0]->join_date ? date('Y-m-d', strtotime($employees[0]->join_date)) : date('Y-m-d'),
            'boss_id' => $employees[0]->boss_id
        ];

        $response = $this->putJson(route('api.employees.updateEmployment', ['employeeUid' => $employees[0]->uid]), $payload, [
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
