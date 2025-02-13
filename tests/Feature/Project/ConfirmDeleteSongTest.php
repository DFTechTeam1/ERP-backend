<?php

namespace Tests\Feature\Project;

use App\Actions\Project\DetailCache;
use App\Services\GeneralService;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Hrd\Models\Employee;
use Modules\Production\Exceptions\SongNotFound;
use Modules\Production\Jobs\ConfirmDeleteSongJob;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class ConfirmDeleteSongTest extends TestCase
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
    public function testSongNotFound(): void
    {
        $generalMock = $this->instance(
            abstract: GeneralService::class,
            instance: Mockery::mock(GeneralService::class)
        );

        $generalMock->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn(1000);

        $this->setProjectConstructor(generalService: $generalMock);

        $response = $this->projectService->confirmDeleteSong('projectuid', 'songuid');

        $this->assertTrue($response['error']);
    }

    public function testDeleteSongIsSuccess(): void
    {
        Bus::fake();

        $employees = Employee::factory()
            ->count(1)
            ->create();

        $projects = Project::factory()
            ->count(1)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'is_request_edit' => 0,
                'is_request_delete' => 1,
                'project_id' => $projects[0]->id,
                'created_by' => 1
            ]);

        EntertainmentTaskSong::factory()
            ->count(1)
            ->create([
                'project_song_list_id' => $projectSongs[0]->id,
                'employee_id' => $employees[0]->id,
                'project_id' => $projects[0]->id
            ]);

        $generalMock = $this->instance(
            abstract: GeneralService::class,
            instance: Mockery::mock(GeneralService::class)
        );

        $generalMock
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projectSongs[0]->id);

        $this->setProjectConstructor(generalService: $generalMock);

        DetailCache::mock()
            ->shouldReceive('handle')
            ->with($projects[0]->uid)
            ->andReturn($projects[0]);

        $response = $this->projectService->confirmDeleteSong($projects[0]->uid, $projectSongs[0]->uid);

        $this->assertFalse($response['error']);

        $this->assertDatabaseMissing('project_song_lists', ['uid' => $projectSongs[0]->uid]);

        Bus::assertDispatched(ConfirmDeleteSongJob::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
