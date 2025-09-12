<?php

use App\Actions\Development\DefineTaskAction;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Development\Models\DevelopmentProjectTaskPic;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Update deadline when task already have deadline', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $currentDeadline = now()->addDays(7)->format('Y-m-d H:i:s');
    $newDeadline = now()->addDays(10)->format('Y-m-d H:i:s');
    $tasks = DevelopmentProjectTask::factory()
        ->withPic(deadline: $currentDeadline)
        ->create();

    $taskPics = DevelopmentProjectTaskPic::where('task_id', $tasks->id)->get();

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $tasks->id,
        'deadline' => $currentDeadline,
    ]);

    $response = $this->postJson(route('api.development.projects.tasks.deadline.update', ['taskUid' => $tasks->uid]), [
        'end_date' => $newDeadline,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data',
    ]);

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $tasks->id,
        'deadline' => $newDeadline,
    ]);

    $this->assertDatabaseHas('development_project_task_deadlines', [
        'task_id' => $tasks->id,
        'actual_end_time' => null,
        'deadline' => $newDeadline,
        'employee_id' => $taskPics->first()->employee_id,
    ]);
});

it('Update deadline when task do not have any deadline', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $newDeadline = now()->addDays(10)->format('Y-m-d H:i:s');

    $task = DevelopmentProjectTask::factory()
        ->withPic()
        ->create();

    $taskPics = DevelopmentProjectTaskPic::where('task_id', $task->id)->get();

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'deadline' => null,
    ]);

    $this->assertDatabaseCount('development_project_task_deadlines', 0);

    $response = $this->postJson(route('api.development.projects.tasks.deadline.update', ['taskUid' => $task->uid]), [
        'end_date' => $newDeadline,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data',
    ]);

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'deadline' => $newDeadline,
    ]);

    $this->assertDatabaseHas('development_project_task_deadlines', [
        'task_id' => $task->id,
        'actual_end_time' => null,
        'deadline' => $newDeadline,
        'employee_id' => $taskPics->first()->employee_id,
    ]);
});
