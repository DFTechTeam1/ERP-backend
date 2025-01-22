<?php

namespace Tests\Feature\Project;

use App\Services\GeneralService;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use Modules\Production\Exceptions\SongNotFound;
use Modules\Production\Jobs\RequestEditSongJob;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Repository\ProjectSongListRepository;
use Modules\Production\Services\ProjectService;
use Tests\TestCase;

use function PHPUnit\Framework\assertStringContainsString;

class UpdateRequestSongTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication, HasProjectConstructor;

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
    public function testUpdateSongMissingParameter(): void
    {
        $payload = [
            'song' => ''
        ];

        $response = $this->putJson(route('api.production.projects.updateSongs', ['projectUid' => '123', 'songUid' => 'songid']), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(422);
    }

    public function testUpdateSongWithSongIsNotFound(): void
    {
        $payload = [
            'song' => 'song 1'
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
                    'task.employee:id,name,nickname'
                ]
            )
            ->andReturnNull()
            ->shouldReceive('update')
            ->atMost(1)
            ->with(
                [
                   'name' => 'song 1'
                ],
                'id'
            )
            ->andReturnTrue();

        $response = $this->putJson(route('api.production.projects.updateSongs', ['projectUid' => 'id', 'songUid' => 'id']), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);
        $response->assertStatus(400);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
