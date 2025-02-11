<?php

namespace Tests\Feature\Project\Entertainment;

use App\Actions\Hrd\PointRecord;
use App\Actions\Project\DetailCache;
use App\Enums\Production\TaskSongStatus;
use App\Enums\System\BaseRole;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\TaskSongApprovedJob;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class SongApproveByPmTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication, HasProjectConstructor;

    private $token;

    private $projects;

    private $projectSongs;

    private $task;

    private $employees;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearUserTables();
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

    protected function registerAuthor(string $roleName)
    {
        $userData = $this->auth(
            roleName: $roleName,
        );

        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);

        $this->token = $this->getToken($userData['user']);
    }

    protected function clearUserTables()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::disableForeignKeyConstraints();

        $tables = [
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
            'permissions',
            'roles',
            'users'
        ];
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * A basic feature test example.
     */
    public function testSongAlreadyApproved(): void
    {
        $this->registerAuthor(roleName: BaseRole::ProjectManagerEntertainment->value);

        $this->seeder(taskStatus: TaskSongStatus::OnLastReview->value);

        $this->setProjectConstructor();

        $response = $this->projectService->songApproveWork($this->projects[0]->uid, $this->projectSongs[0]->uid);
        
        $this->assertTrue($response['error']);

        $this->assertStringContainsString(__('notification.failedToAproveTask'), $response['message']);
    }

    public function testApproveTaskReturnSuccess(): void
    {
        $this->registerAuthor(roleName: BaseRole::ProjectManagerEntertainment->value);

        Bus::fake();

        $this->seeder(taskStatus: TaskSongStatus::OnFirstReview->value);

        $this->setProjectConstructor();

        DetailCache::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturn($this->projects[0]);

        $response = $this->projectService->songApproveWork($this->projects[0]->uid, $this->projectSongs[0]->uid);
        Log::error($response);
        Bus::assertDispatched(TaskSongApprovedJob::class);

        $this->assertFalse($response['error']);

        $this->assertStringContainsString(__('notification.taskSongHasBeenApproved'), $response['message']);

        // $this->assertDatabaseHas('entertainment_task_songs', ['status' => TaskSongStatus::OnFirstReview->value, 'id' => $this->task[0]->id]);

        // check log
        $this->assertDatabaseHas('entertainment_task_song_logs', ['text' => 'log.songApprovedByEntertainmentPM']);

        // check point
        $this->assertDatabaseHas('employee_points', ['employee_id' => $this->employees[0]->id, 'point' => 1, 'additional_point' => 0, 'task_type' => 'entertainment', 'task_id' => $this->task[0]->id]);

        DB::table('employee_points')
            ->truncate();
        
        $this->clearUserTables();
    }

    public function testApproveTaskByEventProjectManager(): void
    {
        $this->registerAuthor(roleName: BaseRole::ProjectManager->value);

        Bus::fake();

        $this->seeder(taskStatus: TaskSongStatus::OnLastReview->value);

        $this->setProjectConstructor();

        DetailCache::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturn($this->projects[0]);

        $response = $this->projectService->songApproveWork($this->projects[0]->uid, $this->projectSongs[0]->uid);

        Bus::assertDispatched(TaskSongApprovedJob::class);

        $this->assertFalse($response['error']);

        $this->assertStringContainsString(__('notification.taskSongHasBeenApproved'), $response['message']);

        $this->assertDatabaseHas('entertainment_task_songs', ['status' => TaskSongStatus::Completed->value, 'id' => $this->task[0]->id]);

        // check log
        $this->assertDatabaseHas('entertainment_task_song_logs', ['text' => 'log.songApprovedByEntertainmentPM']);

        // check point
        $this->assertDatabaseEmpty('employee_points');

        $this->clearUserTables();
    }

    protected function tearDown(): void
    {
        $this->clearUserTables();
        
        parent::tearDown();
    }
}
