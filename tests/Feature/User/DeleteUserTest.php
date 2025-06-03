<?php

namespace Tests\Feature\User;

use App\Models\User;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
    public function test_delete_external_user(): void
    {
        $users = User::factory()->count(1)->create([
            'is_external_user' => 1,
            'employee_id' => null,
        ]);

        $response = $this->postJson(route('api.users.bulk-delete'), [
            'uids' => collect($users)->pluck('uid')->toArray(),
        ], [
            'Authorization' => 'Bearer '.$this->token,
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
