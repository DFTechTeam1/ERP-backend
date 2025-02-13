<?php

namespace Tests\Feature\Actions\Project\Entertainment;

use App\Actions\Project\Entertainment\StoreLogAction;
use App\Enums\Production\Entertainment\TaskSongLogType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Tests\TestCase;

class StoreLogActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * A basic feature test example.
     */
    public function testStoreDataIsSuccess(): void
    {
        $type = TaskSongLogType::ApprovedRequestEdit->value;

        $projects = Project::factory()
            ->count(1)
            ->create();

        $projectSongs = ProjectSongList::factory()
            ->count(1)
            ->create([
                'project_id' => $projects[0]->id,
                'created_by' => 1
            ]);

        $paramText = [
            'pm' => 'user',
            'event' => 'event',
            'currentName' => 'name1',
            'newName' => 'new name'
        ];
        
        StoreLogAction::run(
            $type,
            [
                'project_song_list_id' => $projectSongs[0]->id,
                'entertainment_task_song_id' => 0,
                'project_id' => $projects[0]->id,
                'employee_id' => null,
            ],
            $paramText
        );

        $this->assertDatabaseHas('entertainment_task_song_logs', [
            'project_song_list_id' => $projectSongs[0]->id,
            'project_id' => $projects[0]->id,
            'text' => 'log.songApproveRequestEdit'
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
