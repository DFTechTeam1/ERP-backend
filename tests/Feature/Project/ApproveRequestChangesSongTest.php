<?php

namespace Tests\Feature\Project;

use App\Actions\Project\DetailCache;
use App\Services\GeneralService;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\SongApprovedToBeEditedJob;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class ApproveRequestChangesSongTest extends TestCase
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
    public function test_confirm_edit_song_success(): void
    {
        Bus::fake();

        $projects = Project::factory()
            ->count(1)
            ->create();

        $employees = Employee::factory()
            ->count(1)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'created_by' => 1,
                'is_request_edit' => 1,
                'is_request_delete' => 0,
                'target_name' => 'new song',
            ]);

        EntertainmentTaskSong::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'employee_id' => $employees[0]->id,
                'project_song_list_id' => $projectSongs[0]->id,
            ]);

        $generalMock = $this->instance(
            abstract: GeneralService::class,
            instance: Mockery::mock(GeneralService::class)
        );

        $generalMock->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projectSongs[0]->id)
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projects[0]->id);

        // mock action
        DetailCache::mock()
            ->shouldReceive('handle')
            ->with($projects[0]->uid)
            ->andReturn($projects[0]);

        $this->setProjectConstructor(
            generalService: $generalMock
        );

        $response = $this->projectService->confirmEditSong($projects[0]->uid, $projectSongs[0]->uid);

        $this->assertFalse($response['error']);
        $this->assertDatabaseHas('project_song_lists', [
            'target_name' => null,
            'uid' => $projectSongs[0]->uid,
            'name' => 'new song',
            'is_request_edit' => false,
            'is_request_delete' => false,
        ]);

        Bus::assertDispatched(SongApprovedToBeEditedJob::class);
    }

    public function test_song_not_found()
    {
        $generalMock = $this->instance(
            abstract: GeneralService::class,
            instance: Mockery::mock(GeneralService::class)
        );

        $generalMock->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn(1)
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn(1);

        $this->setProjectConstructor(
            generalService: $generalMock
        );

        $response = $this->projectService->confirmEditSong('uid', 'songuid');

        $this->assertTrue($response['error']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
