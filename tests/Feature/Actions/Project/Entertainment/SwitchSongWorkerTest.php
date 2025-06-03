<?php

namespace Tests\Feature\Actions\Project\Entertainment;

use App\Actions\Project\Entertainment\SwitchSongWorker;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\DistributeSongJob;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class SwitchSongWorkerTest extends TestCase
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
    public function test_switch_worker_success(): void
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
                'created_by' => 1,
            ]);

        $task = EntertainmentTaskSong::factory()
            ->count(1)
            ->create([
                'project_song_list_id' => $projectSongs[0]->id,
                'project_id' => $projects[0]->id,
                'employee_id' => $employees[0]->id,
            ]);

        $nextWorker = $employees[1]->uid;

        $response = SwitchSongWorker::run($nextWorker, $projectSongs[0]->uid);

        // check main database
        $this->assertDatabaseHas('entertainment_task_songs', [
            'employee_id' => $employees[1]->id,
            'project_song_list_id' => $projectSongs[0]->id,
        ]);

        // check log database for remove worker
        $this->assertDatabaseHas('entertainment_task_song_logs', [
            'project_id' => $projects[0]->id,
            'project_song_list_id' => $projectSongs[0]->id,
            'text' => 'log.songRemoveWorker',
        ]);

        // check log for new worker
        $this->assertDatabaseHas('entertainment_task_song_logs', [
            'project_id' => $projects[0]->id,
            'project_song_list_id' => $projectSongs[0]->id,
            'text' => 'log.userHasBeenAssigned',
        ]);

        $this->assertFalse($response['error']);

        Bus::assertDispatched(DistributeSongJob::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
