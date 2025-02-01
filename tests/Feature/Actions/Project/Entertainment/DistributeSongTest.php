<?php

namespace Tests\Feature\Actions\Project\Entertainment;

use App\Actions\Project\Entertainment\DistributeSong;
use App\Services\GeneralService;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\DistributeSongJob;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class DistributeSongTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();
        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);
    }

    /**
     * A basic feature test example.
     */
    public function testDistributeSongSuccess(): void
    {
        Bus::fake();
        
        $employees = Employee::factory()
            ->count(2)
            ->create();

        $projects = Project::factory()
            ->count(1)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'is_request_edit' => 0,
                'is_request_delete' => 0,
                'created_by' => 1,
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

        $generalMock->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($employees[1]->id)
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projectSongs[0]->id)
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projects[0]->id);

        $payload = [
            'employee_uid' => $employees[1]->uid
        ];

        DistributeSong::run($payload, $projects[0]->uid, $projectSongs[0]->uid, $generalMock);

        $this->assertDatabaseHas('entertainment_task_songs', [
            'project_song_list_id' => $projectSongs[0]->id,
            'employee_id' => $employees[1]->id,
        ]);

        Bus::assertDispatched(DistributeSongJob::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
