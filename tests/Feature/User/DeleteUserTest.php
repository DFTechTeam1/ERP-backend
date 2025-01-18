<?php

namespace Tests\Feature\User;

use App\Models\User;
use App\Repository\UserRepository;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Tests\TestCase;

class DeleteUserTest extends TestCase
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
    public function testDeleteExternalUser(): void
    {
        $users = User::factory()->count(1)->create([
            'is_external_user' => 1,
            'employee_id' => NULL,
        ]);

        $response = $this->postJson(route('api.users.bulk-delete'), [
            'uids' => collect($users)->pluck('uid')->toArray()
        ], [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(201);

        $this->assertNull(User::find($users[0]->id));

        parent::tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
