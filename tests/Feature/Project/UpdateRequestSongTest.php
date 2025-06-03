<?php

namespace Tests\Feature\Project;

use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Production\Repository\ProjectSongListRepository;
use Tests\TestCase;

class UpdateRequestSongTest extends TestCase
{
    use HasProjectConstructor, RefreshDatabase, TestUserAuthentication;

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
    public function test_update_song_missing_parameter(): void
    {
        $payload = [
            'song' => '',
        ];

        $response = $this->putJson(route('api.production.projects.updateSongs', ['projectUid' => '123', 'songUid' => 'songid']), $payload, [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_song_with_song_is_not_found(): void
    {
        $payload = [
            'song' => 'song 1',
        ];

        $mockRepo = Mockery::mock(ProjectSongListRepository::class);

        $mock = $this->instance(
            abstract: ProjectSongListRepository::class,
            instance: $mockRepo
        );

        $mock->shouldReceive('show')
            ->atMost(1)
            ->with(
                'id',
                'id,project_id,name',
                [
                    'task:id,project_song_list_id,employee_id',
                    'task.employee:id,name,nickname',
                ]
            )
            ->andReturnNull()
            ->shouldReceive('update')
            ->atMost(1)
            ->with(
                [
                    'name' => 'song 1',
                ],
                'id'
            )
            ->andReturnTrue();

        $response = $this->putJson(route('api.production.projects.updateSongs', ['projectUid' => 'id', 'songUid' => 'id']), $payload, [
            'Authorization' => 'Bearer '.$this->token,
        ]);
        $response->assertStatus(400);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
