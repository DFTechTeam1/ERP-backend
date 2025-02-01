<?php

namespace Tests\Feature\Project;

use App\Actions\Project\DetailCache;
use App\Actions\Project\Entertainment\StoreLogAction;
use App\Services\GeneralService;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\Project\RejectRequestEditSongJob;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class RejectEditSongTest extends TestCase
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

    public function testRejectEditSongWithLengthOfReasonMoreThan250(): void
    {
        $response = $this->postJson(
            route('api.production.projects.rejectEditSong', ['projectUid' => 'id', 'songUid' => 'id']),
            [
                'reason' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.Lorem ipsum dolor sit amet consectetur adipisicing elit. Nesciunt voluptatibus quidem facere quaerat tempora aperiam quasi optio ipsam, suscipit dolores, iure dicta provident molestias enim. Vitae nihil voluptatem quis delectus.'
            ],
            [
                'Authorization' => 'Bearer ' . $this->token
            ]
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
    }

    /**
     * A basic feature test example.
     */
    public function testRejectEditSongIsSuccess(): void
    {
        Bus::fake();

        $generalMock = $this->instance(
            abstract: GeneralService::class,
            instance: Mockery::mock(GeneralService::class)
        );

        $payload = [
            'reason' => 'tidak mau'
        ];

        $projects = Project::factory()
            ->count(1)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'is_request_edit' => 1,
                'is_request_delete' => 0,
                'target_name' => 'ubah',
                'created_by' => 1
            ]);

        $generalMock->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projectSongs[0]->id)
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projects[0]->id);

        $this->setProjectConstructor(generalService: $generalMock);

        StoreLogAction::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturnTrue();

        DetailCache::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturn($projects[0]);

        $response = $this->projectService->rejectEditSong($payload, $projects[0]->uid, $projectSongs[0]->uid);

        $this->assertFalse($response['error']);

        $this->assertDatabaseHas('project_song_lists', [
            'reason' => $payload['reason'],
            'is_request_edit' => 0,
            'is_request_delete' => 0,
            'target_name' => null,
            'id' => $projectSongs[0]->id
        ]);

        DetailCache::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturn($projects[0]);

        Bus::assertDispatched(RejectRequestEditSongJob::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
