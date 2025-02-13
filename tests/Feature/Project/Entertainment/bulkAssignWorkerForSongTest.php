<?php

namespace Tests\Feature\Project\Entertainment;

use App\Actions\Project\DetailCache;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class bulkAssignWorkerForSongTest extends TestCase
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
    public function testUniqueSongDetected(): void
    {
        $payload = [
            'workers' => [
                [
                    'uid' => '123',
                    'songs' => [
                        1,2
                    ]
                ],
                [
                    'uid' => '1234',
                    'songs' => [
                        3,2
                    ]
                ],
            ]
        ];

        $this->setProjectConstructor();

        $response = $this->projectService->bulkAssignWorkerForSong($payload, 'uid');

        $this->assertStringContainsString(__('notification.duplicateSongOnBulkAssign'), $response['message']);
    }

    public function testBulkAssignIsSuccess(): void
    {
        $employees = Employee::factory()
            ->count(2)
            ->create();

        $projects = Project::factory()
            ->count(1)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(10)
            ->create([
                'project_id' => $projects[0]->id,
                'created_by' => 1
            ]);

        $payload = [];
        foreach ($employees as $key => $employee) {
            if ($key == 0) {
                $payload['workers'][] = [
                    'uid' => $employee->uid,
                    'songs' => [
                        $projectSongs[0]->uid,
                        $projectSongs[1]->uid,
                        $projectSongs[2]->uid,
                    ]
                ];
            }

            if ($key == 1) {
                $payload['workers'][] = [
                    'uid' => $employee->uid,
                    'songs' => [
                        $projectSongs[3]->uid,
                        $projectSongs[4]->uid,
                        $projectSongs[5]->uid,
                    ]
                ];
            }
        }

        // mock action
        DetailCache::mock()
            ->shouldReceive('handle')
            ->withAnyArgs()
            ->andReturn($projects[0]);

        $this->setProjectConstructor();

        $response = $this->projectService->bulkAssignWorkerForSong($payload, $projects[0]->uid);

        $this->assertFalse($response['error']);

        $this->assertDatabaseHas('entertainment_task_songs', [
            'employee_id' => $employees[0]->id,
            'project_song_list_id' => $projectSongs[0]->id
        ]);
        $this->assertDatabaseHas('entertainment_task_songs', [
            'employee_id' => $employees[0]->id,
            'project_song_list_id' => $projectSongs[1]->id
        ]);
        $this->assertDatabaseHas('entertainment_task_songs', [
            'employee_id' => $employees[1]->id,
            'project_song_list_id' => $projectSongs[3]->id
        ]);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('full_detail', $response['data']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
