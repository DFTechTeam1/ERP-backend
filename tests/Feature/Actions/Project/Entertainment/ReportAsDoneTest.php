<?php

namespace Tests\Feature\Actions\Project\Entertainment;

use App\Actions\Project\Entertainment\ReportAsDone;
use App\Exceptions\UploadImageFailed;
use App\Services\GeneralService;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\SongReportAsDone;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class ReportAsDoneTest extends TestCase
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
    public function testFailedUploadFile(): void
    {
        $projects = Project::factory()
            ->count(1)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'created_by' => 1
            ]);

        $generalMock = $this->instance(
            abstract: GeneralService::class,
            instance: Mockery::mock(GeneralService::class)
        );

        $generalMock->shouldReceive('uploadImageandCompress')
            ->atMost(2)
            ->withAnyArgs()
            ->andReturnFalse()
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projects[0]->id)
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projectSongs[0]->id);

        $this->expectException(UploadImageFailed::class);

        ReportAsDone::run(['images' => ['image' => 1]], $projects[0]->uid, $projectSongs[0]->uid, $generalMock);
    }

    public function testReportIsSuccess(): void
    {
        $projects = Project::factory()
            ->count(1)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'created_by' => 1
            ]);

        $employees = Employee::factory()
            ->count(1)
            ->create();

        $task = EntertainmentTaskSong::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'project_song_list_id' => $projectSongs[0]->id,
                'employee_id' => $employees[0]->id
            ]);

        $generalMock = $this->instance(
            abstract: GeneralService::class,
            instance: Mockery::mock(GeneralService::class)
        );

        $generalMock->shouldReceive('uploadImageandCompress')
            ->atMost(2)
            ->withAnyArgs()
            ->andReturn('image.png')
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projects[0]->id)
            ->shouldReceive('getIdFromUid')
            ->once()
            ->withAnyArgs()
            ->andReturn($projectSongs[0]->id);

        $this->setProjectConstructor(generalService: $generalMock);

        ReportAsDone::run(
            [
                'images' => [
                    'images.png'
                ],
                'nas_path' => 'http://nas-path',
            ],
            $projects[0]->uid,
            $projectSongs[0]->uid,
            $generalMock
        );

        $this->assertDatabaseHas('entertainment_task_song_results', [
            'employee_id' => $employees[0]->id,
            'nas_path' => 'http://nas-path',
            'task_id' => $task[0]->id
        ]);

        $this->assertDatabaseHas('entertainment_task_song_result_images', [
            'path' => 'image.png',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
