<?php

namespace Tests\Feature\Project;

use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Production\Models\Project;
use Tests\TestCase;

class StoreReferenceTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication;

    private $user;

    private $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();
        $this->user = $userData['user'];
        $this->employee = $userData['employee'];

        Sanctum::actingAs($this->user);
        $this->actingAs($this->user);
    }

    /**
     * A basic feature test example.
     */
    public function test_payload_is_missing(): void
    {
        $project = Project::factory()->count(1)->create();
        $payload = [
            'link' => [
                ['href' => 'google.com'],
            ],
        ];

        $response = $this->postJson(route('api.production.store-reference', ['id' => $project[0]->uid]), $payload, [
            'Authorization' => 'Bearer '.$this->getToken($this->user),
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors',
        ]);

        parent::tearDown();
    }

    public function test_upload_only_link_return_success(): void
    {
        $project = Project::factory()->count(1)->create();
        $payload = [
            'link' => [
                ['href' => 'https://google.com', 'name' => 'google.com'],
            ],
        ];

        $response = $this->post(route('api.production.store-reference', ['id' => $project[0]->uid]), $payload, [
            'Authorization' => 'Bearer '.$this->getToken($this->user),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'full_detail',
                'references',
            ],
        ]);
        $this->assertDatabaseHas('project_references', [
            'media_path' => 'https://google.com',
            'name' => 'google.com',
            'type' => 'link',
        ]);

        parent::tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
