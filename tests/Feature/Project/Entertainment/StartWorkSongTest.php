<?php

namespace Tests\Feature\Project\Entertainment;

use App\Actions\Project\DetailCache;
use App\Enums\Production\TaskSongStatus;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class StartWorkSongTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication, HasProjectConstructor;

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
    public function testSongAlreadyInProgress(): void
    {
        $projects = Project::factory()
            ->count(1)
            ->create();

        $employees = Employee::factory()
            ->count(2)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'created_by' => 1,
            ]);

        $task = EntertainmentTaskSong::factory()
            ->count(1)
            ->create([
                'project_song_list_id' => $projectSongs[0]->id,
                'employee_id' => $employees[0]->id,
                'project_id' => $projects[0]->id,
                'status' => TaskSongStatus::OnProgress->value
            ]);

        $this->setProjectConstructor();

        $response = $this->projectService->startWorkOnSong($projects[0]->uid, $projectSongs[0]->uid);

        $this->assertTrue($response['error']);
        $this->assertStringContainsString(__('notification.songAlreadyInProgress'), $response['message']);
    }

    public function testSongStartWorkReturnSuccess(): void
    {
        $projects = Project::factory()
            ->count(1)
            ->create();

        $employees = Employee::factory()
            ->count(2)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'created_by' => 1,
            ]);

        $task = EntertainmentTaskSong::factory()
            ->count(1)
            ->create([
                'project_song_list_id' => $projectSongs[0]->id,
                'employee_id' => $employees[0]->id,
                'project_id' => $projects[0]->id,
                'status' => TaskSongStatus::Active->value
            ]);

        $this->setProjectConstructor();

        DetailCache::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturn($projects[0]);

        $response = $this->projectService->startWorkOnSong($projects[0]->uid, $projectSongs[0]->uid);

        $this->assertFalse($response['error']);
        $this->assertDatabaseHas('entertainment_task_songs', [
            'status' => TaskSongStatus::OnProgress->value,
            'id' => $task[0]->id
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
