<?php

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Development\Project\Task\TaskStatus;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;

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

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = InteractiveProjectTask::factory()
        ->withPic($deadline)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value,
        ]);

    $response = $this->deleteJson(route('api.production.interactives.tasks.destroy', $task->uid));

    $response->assertStatus(201);

    $this->assertDatabaseMissing('intr_project_tasks', [
        'id' => $task->id,
    ]);

    // development project task pics is empty
    $this->assertDatabaseMissing('intr_project_task_pics', [
        'task_id' => $task->id,
    ]);

    // development project task deadlines is empty
    $this->assertDatabaseMissing('intr_project_task_deadlines', [
        'task_id' => $task->id,
    ]);

    // development project task attachments is empty
    $this->assertDatabaseMissing('intr_project_task_attachments', [
        'intr_project_task_id' => $task->id,
    ]);
});
