<?php

namespace Tests\Feature\Project;

use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Modules\Production\Jobs\RequestSongJob;
use Modules\Production\Models\Project;
use Modules\Production\Notifications\RequestSongNotification;
use Tests\TestCase;

class RequestSongTest extends TestCase
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
    public function testStoreSongWithMissingParameter(): void
    {
        $payload = [
            'songs' => []
        ];

        $response = $this->postJson(route('api.production.projects.storeSongs', ['projectUid' => '123']), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
    }

    public function testStoreSongReturnSuccess(): void
    {
        Bus::fake();

        $projects = Project::factory()
            ->count(1)
            ->create();

        $project = $projects[0];

        $payload = [
            'songs' => [
                'Lagu 1',
                'Lagu 2'
            ]
        ];

        $response = $this->postJson(route('api.production.projects.storeSongs', ['projectUid' => $project->uid]), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(201);
        $this->assertStringContainsString(__('notification.songHasBeenAdded'), $response['message']);

        Bus::assertDispatched(RequestSongJob::class);

        parent::tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
