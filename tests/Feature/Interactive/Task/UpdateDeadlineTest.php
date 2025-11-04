<?php

use App\Actions\Interactive\DefineTaskAction;
use Modules\Production\Models\InteractiveProjectTask;
use Modules\Production\Models\InteractiveProjectTaskPic;

beforeEach(function () {
    $user = initAuthenticateUser(
        permissions: ['update_deadline_interactive_task']
    );

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
    $tasks = InteractiveProjectTask::factory()
        ->withPic(deadline: $currentDeadline)
        ->create();

    $taskPics = InteractiveProjectTaskPic::where('task_id', $tasks->id)->get();

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $tasks->id,
        'deadline' => $currentDeadline,
    ]);

    $response = $this->postJson(route('api.production.interactives.tasks.deadline.update', ['taskUid' => $tasks->uid]), [
        'end_date' => $newDeadline,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data',
    ]);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $tasks->id,
        'deadline' => $newDeadline,
    ]);

    $this->assertDatabaseHas('intr_project_task_deadlines', [
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

    $task = InteractiveProjectTask::factory()
        ->withPic()
        ->create([
            'deadline' => null,
        ]);

    $taskPics = InteractiveProjectTaskPic::where('task_id', $task->id)->get();

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'deadline' => null,
    ]);

    $this->assertDatabaseCount('intr_project_task_deadlines', 0);

    $response = $this->postJson(route('api.production.interactives.tasks.deadline.update', ['taskUid' => $task->uid]), [
        'end_date' => $newDeadline,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data',
    ]);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'deadline' => $newDeadline,
    ]);

    $this->assertDatabaseHas('intr_project_task_deadlines', [
        'task_id' => $task->id,
        'actual_end_time' => null,
        'deadline' => $newDeadline,
        'employee_id' => $taskPics->first()->employee_id,
    ]);
});
