<?php

use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Hrd\Models\Employee;
use App\Enums\Development\Project\Task\TaskStatus;
use App\Actions\Development\DefineTaskAction;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Delete task that have pics on it', function () {
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
            'status' => TaskStatus::InProgress->value
        ]);

    $response = $this->deleteJson(route('api.development.projects.tasks.destroy', $task->uid));

    $response->assertStatus(201);

    $this->assertDatabaseMissing('development_project_tasks', [
        'id' => $task->id
    ]);

    // development project task pics is empty
    $this->assertDatabaseMissing('development_project_task_pics', [
        'task_id' => $task->id
    ]);

    // development project task deadlines is empty
    $this->assertDatabaseMissing('development_project_task_deadlines', [
        'task_id' => $task->id
    ]);

    // development project task attachments is empty
    $this->assertDatabaseMissing('development_project_task_attachments', [
        'task_id' => $task->id
    ]);
});
