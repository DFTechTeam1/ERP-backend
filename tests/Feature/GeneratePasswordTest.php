<?php

namespace Tests\Feature;

use App\Services\GeneralService;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeneratePasswordTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication;

    private $service;

    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GeneralService();

        $userData = $this->auth();
        
        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);

        $this->token = $this->getToken($userData['user']);
    }

    /**
     * A basic feature test example.
     */
    public function testPasswordLength(): void
    {
        $length = 10;
        $password = $this->service->generateRandomPassword($length);

        $this->assertTrue(strlen($password) == $length);
        $this->assertTrue(is_string($password));
    }

    public function testRouteToGeneratePassword(): void
    {
        $response = $this->getJson(route('api.employees.generateRandomPassword'), [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'password'
            ]
        ]);
    }
}
