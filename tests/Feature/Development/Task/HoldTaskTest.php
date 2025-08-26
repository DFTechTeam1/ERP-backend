<?php

use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Development\Models\DevelopmentProjectTaskPicWorkstate;
use Modules\Hrd\Models\Employee;
use App\Enums\Development\Project\Task\TaskStatus;
use App\Actions\Development\DefineTaskAction;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

function mainHoldTaskSeeder($user)
{
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
            'user_id' => $user->id
        ]);

    // update users employee_id
    \App\Models\User::where('id', $user->id)->update([
        'employee_id' => $worker->id
    ]);

    $task = DevelopmentProjectTask::factory()
        ->withPic(employee: $worker, withWorkState: true)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => TaskStatus::InProgress->value
        ]);

    return [
        'task' => $task,
        'worker' => $worker
    ];
}

it ('Hold existing task', function () {
    $data = mainHoldTaskSeeder($this->user);
    $task = $data['task'];
    $worker = $data['worker'];

    $this->assertDatabaseCount('dev_project_task_pic_workstates', 1);
    $this->assertDatabaseHas('dev_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id
    ]);

    // hold the task
    $response = $this->getJson(route('api.development.projects.tasks.holded', $task->uid));

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data'
    ]);

    // get current workstate
    $workState = DevelopmentProjectTaskPicWorkstate::where('task_id', $task->id)
        ->where('employee_id', $worker->id)
        ->first();

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::OnHold->value
    ]);

    $this->assertDatabaseHas('dev_project_task_pic_holdstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'work_state_id' => $workState->id
    ]);
    $this->assertDatabaseMissing('dev_project_task_pic_holdstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'holded_at' => null
    ]);
});