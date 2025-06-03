<?php

namespace Tests\Feature\User;

use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserListTest extends TestCase
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
    public function test_list_user_without_any_parameters(): void
    {
        $response = $this->getJson(route('api.users.index'), [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'paginated',
                'totalData',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
