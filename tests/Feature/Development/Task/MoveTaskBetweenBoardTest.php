<?php

use App\Actions\Development\DefineTaskAction;
use App\Enums\Development\Project\Task\TaskStatus;
use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Move task successfully', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = DevelopmentProjectTask::factory()
        ->withPic($deadline)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value,
        ]);

    $targetBoardId = $project->boards->last()->id;

    $response = $this->getJson(route('api.development.projects.tasks.move', ['taskUid' => $task->uid, 'boardId' => $targetBoardId]));

    $response->assertStatus(201);

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'development_project_board_id' => $targetBoardId,
    ]);
});
