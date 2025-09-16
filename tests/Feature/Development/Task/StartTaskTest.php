<?php

use App\Actions\Development\DefineTaskAction;
use App\Enums\Development\Project\Task\TaskStatus;
use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Hrd\Models\Employee;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it('Start task after hold', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = DevelopmentProjectTask::factory()
        ->withPic(employee: $worker, withWorkState: true)
        ->withHoldState()
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => TaskStatus::InProgress->value,
        ]);

    $this->assertDatabaseHas('development_project_tasks', [
        'status' => TaskStatus::OnHold->value,
        'id' => $task->id,
    ]);

    // start the task
    $response = $this->getJson(route('api.development.projects.tasks.start', $task->uid));

    $response->assertStatus(201);

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::InProgress->value,
    ]);

    $this->assertDatabaseMissing('dev_project_task_pic_holdstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'unholded_at' => null,
    ]);

    $this->assertDatabaseHas('dev_project_task_pic_holdstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);
});
