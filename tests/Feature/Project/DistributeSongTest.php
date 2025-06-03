<?php

namespace Tests\Feature\Project;

use App\Actions\Project\DetailCache;
use App\Actions\Project\Entertainment\DistributeSong;
use App\Actions\Project\Entertainment\StoreLogAction;
use App\Services\GeneralService;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class DistributeSongTest extends TestCase
{
    use HasProjectConstructor, RefreshDatabase, TestUserAuthentication;

    private $token;

    private $generalMock;

    private $employees;

    private $projects;

    private $projectSongs;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();
        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);

        $this->token = $this->getToken($userData['user']);

        $this->generalMock = $this->instance(
            abstract: GeneralService::class,
            instance: Mockery::mock(GeneralService::class)
        );
    }

    /**
     * A basic feature test example.
     */
    public function test_distribute_with_deleted_song(): void
    {
        $this->generalMock->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn(1);

        $this->setProjectConstructor(generalService: $this->generalMock);

        $response = $this->projectService->distributeSong(['employee_uid' => 1], 'projectuid', 'songuid');

        $this->assertTrue($response['error']);

        $this->assertStringContainsString(__('notification.songNotFound'), $response['message']);
    }

    protected function runningSeed(bool $withTask = true)
    {
        $this->employees = Employee::factory()
            ->count(2)
            ->create();

        $this->projects = Project::factory()
            ->count(1)
            ->create();

        $this->projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $this->projects[0]->id,
                'is_request_edit' => 0,
                'is_request_delete' => 0,
                'created_by' => 1,
            ]);

        if ($withTask) {
            EntertainmentTaskSong::factory()
                ->count(1)
                ->create([
                    'project_song_list_id' => $this->projectSongs[0]->id,
                    'employee_id' => $this->employees[0]->id,
                    'project_id' => $this->projects[0]->id,
                ]);
        }
    }

    public function test_distribute_song_to_prevent_double_job(): void
    {
        $this->runningSeed(withTask: true);

        $this->generalMock->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($this->employees[0]->id);

        $this->setProjectConstructor(generalService: $this->generalMock);

        $payload = [
            'employee_uid' => $this->employees[0]->uid,
        ];

        $response = $this->projectService->distributeSong($payload, $this->projects[0]->uid, $this->projectSongs[0]->uid);

        $this->assertFalse($response['error']);
        $this->assertStringContainsString(__('notification.employeeAlreadyAssignedForThisSong', ['name' => $this->employees[0]->nickname]), $response['message']);
    }

    public function test_distribute_song_success(): void
    {
        $this->runningSeed(withTask: false);

        $this->generalMock->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($this->employees[1]->id);

        $this->setProjectConstructor(generalService: $this->generalMock);

        $payload = [
            'employee_uid' => $this->employees[1]->uid,
        ];

        // mock action
        StoreLogAction::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturnTrue();

        DetailCache::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturn($this->projects[0]);

        DistributeSong::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturnTrue();

        $response = $this->projectService->distributeSong($payload, $this->projects[0]->uid, $this->projectSongs[0]->uid);

        $this->assertFalse($response['error']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
