<?php

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Interactive\InteractiveTaskStatus;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;

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

    $project = InteractiveProject::factory()
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

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker, withWorkState: true)
        ->withHoldState()
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    $this->assertDatabaseHas('intr_project_tasks', [
        'status' => InteractiveTaskStatus::OnHold->value,
        'id' => $task->id,
    ]);

    // start the task
    $response = $this->getJson(route('api.production.interactives.tasks.start', $task->uid));

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::InProgress->value,
    ]);

    $this->assertDatabaseMissing('intr_project_task_pic_holdstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'unholded_at' => null,
    ]);

    $this->assertDatabaseHas('intr_project_task_pic_holdstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);
});
