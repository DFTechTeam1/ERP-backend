<?php

use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Hrd\Models\Employee;
use App\Enums\Development\Project\Task\TaskStatus;
use App\Actions\Development\DefineTaskAction;

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

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id
    ]);

    $task = DevelopmentProjectTask::factory()
        ->withPic(employee: $worker)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::WaitingApproval->value
        ]);

    $this->assertDatabaseCount('dev_project_task_pic_workstates', 0);

    // approve task
    $response = $this->get(route('api.development.projects.tasks.approved', $task->uid));

    $response->assertStatus(201);

    $this->assertDatabaseHas('development_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $worker->id
    ]);

    // check workstates
    $this->assertDatabaseHas('dev_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id
    ]);

    // check task status
    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::InProgress->value
    ]);

    // check response format
    $response->assertJsonStructure([
        'message',
        'data'
    ]);
});