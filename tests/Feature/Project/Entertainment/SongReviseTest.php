<?php

namespace Tests\Feature\Project\Entertainment;

use App\Actions\Project\DetailCache;
use App\Enums\Production\TaskSongStatus;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\SongReviseJob;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class SongReviseTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication, HasProjectConstructor;

    private $projects;

    private $projectSongs;

    private $employees;

    private $task;

    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();
        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);

        $this->token = $this->getToken($userData['user']);
    }

    protected function seeder(int $taskStatus)
    {
        $this->projects = Project::factory()
            ->count(1)
            ->create();

        $this->projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $this->projects[0]->id,
                'created_by' => 1
            ]);

        $this->employees = Employee::factory()
            ->count(1)
            ->create();

        $this->task = EntertainmentTaskSong::factory()
            ->count(1)
            ->create([
                'project_id' => $this->projects[0]->id,
                'project_song_list_id' => $this->projectSongs[0]->id,
                'employee_id' => $this->employees[0]->id,
                'status' => $taskStatus
            ]);
    }

    /**
     * A basic feature test example.
     */
    public function testSongAlreadyRevise(): void
    {
        $this->seeder(taskStatus: TaskSongStatus::Revise->value);

        $this->setProjectConstructor();

        $response = $this->projectService->songRevise([
            'reason' => 'tidak cocok',
        ], $this->projects[0]->uid, $this->projectSongs[0]->uid);

        $this->assertTrue($response['error']);

        $this->assertStringContainsString(__('notification.songAlreadyOnRevise'), $response['message']);
    }

    public function testSongReviseReturnFailed(): void
    {
        $payload = [
            'reason' => 'tidak cocok'
        ];

        $this->seeder(taskStatus: TaskSongStatus::OnProgress->value);

        $this->setProjectConstructor();

        $response = $this->projectService->songRevise($payload, $this->projects[0]->uid, $this->projectSongs[0]->uid);

        $this->assertTrue($response['error']);

        $this->assertStringContainsString(__('notification.songCannotBeRevise'), $response['message']);
    }

    public function testSongReviseReturnSuccessWithoutImage()
    {
        Bus::fake();

        $payload = [
            'reason' => 'tidak cocok'
        ];

        $this->seeder(taskStatus: TaskSongStatus::OnFirstReview->value);

        $this->setProjectConstructor();

        DetailCache::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturn($this->projects[0]);

        $response = $this->projectService->songRevise($payload, $this->projects[0]->uid, $this->projectSongs[0]->uid);
        Log::error($response);
        $this->assertFalse($response['error']);

        $this->assertDatabaseHas('entertainment_task_songs', ['employee_id' => $this->employees[0]->id, 'status' => TaskSongStatus::Revise->value, 'id' => $this->task[0]->id]);

        $this->assertDatabaseHas('entertainment_task_song_revises', ['reason' => $payload['reason'], 'entertainment_task_song_id' => $this->task[0]->id]);

        $this->assertDatabaseHas('entertainment_task_song_logs', ['text' => 'log.songRevisedByPM']);

        Bus::assertDispatched(SongReviseJob::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
