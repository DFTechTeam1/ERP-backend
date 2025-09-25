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

it('Approve task return success', function () {
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

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::WaitingApproval->value,
        ]);

    $this->assertDatabaseCount('intr_project_task_pic_workstates', 0);

    // approve task
    $response = $this->get(route('api.production.interactives.tasks.approved', $task->uid));

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    // check workstates
    $this->assertDatabaseHas('intr_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    // check task status
    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::InProgress->value,
    ]);

    // check response format
    $response->assertJsonStructure([
        'message',
        'data',
    ]);
});
