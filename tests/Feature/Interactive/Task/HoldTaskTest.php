<?php

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Interactive\InteractiveTaskStatus;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;
use Modules\Production\Models\InteractiveProjectTaskPicWorkstate;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

function mainHoldInteractiveTaskSeeder($user)
{
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
            'user_id' => $user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker, withWorkState: true)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    return [
        'task' => $task,
        'worker' => $worker,
    ];
}

it('Hold existing task', function () {
    $data = mainHoldInteractiveTaskSeeder($this->user);
    $task = $data['task'];
    $worker = $data['worker'];

    $this->assertDatabaseCount('intr_project_task_pic_workstates', 1);
    $this->assertDatabaseHas('intr_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    // hold the task
    $response = $this->postJson(route('api.production.interactives.tasks.holded', $task->uid), [
        'reason' => 'batal',
    ]);
    logging('HOLD TASK RESPONSE: ', $response->json());

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data',
    ]);

    // get current workstate
    $workState = InteractiveProjectTaskPicWorkstate::where('task_id', $task->id)
        ->where('employee_id', $worker->id)
        ->first();

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::OnHold->value,
    ]);

    $this->assertDatabaseHas('intr_project_task_pic_holdstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'work_state_id' => $workState->id,
        'reason' => 'batal',
    ]);
    $this->assertDatabaseMissing('intr_project_task_pic_holdstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'holded_at' => null,
    ]);
});
