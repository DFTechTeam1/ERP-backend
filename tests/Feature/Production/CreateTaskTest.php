<?php

// api.production.storeTask

use App\Actions\Project\DetailCache;
use Modules\Production\Models\Project;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true,
        permissions: [
            'move_board',
            'edit_task_description',
            'add_task_description',
            'delete_task_description',
            'assign_modeller'
        ]
    );

    $this->actingAs($this->user);

    $this->project = Project::factory()
        ->withBoards()
        ->create();
});

it ("Create task with missing parameters", function () {
    $response = $this->postJson(route('api.production.storeTask', [
        'boardId' => $this->project->boards->first()->id
    ]), [
        // missing parameters
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'name'
    ]);
});

it ('Create task without pic', function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $response = $this->postJson(route('api.production.storeTask', [
        'boardId' => $this->project->boards->first()->id
    ]), [
        'name' => 'New Task',
        'end_date' => now()->addDays(7)->toDateString(),
        // no pic
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_tasks', [
        'name' => 'New Task',
        'project_board_id' => $this->project->boards->first()->id,
    ]);

    $task = \Modules\Production\Models\ProjectTask::where('name', 'New Task')->first();

    $this->assertDatabaseCount('project_task_deadlines', 0);

    $this->assertDatabaseMissing('project_task_pics', [
        'project_task_id' => $task->id,
    ]);
});

it ('Create task with pic', function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $employee = \Modules\Hrd\Models\Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.production.storeTask', [
        'boardId' => $this->project->boards->first()->id
    ]), [
        'name' => 'New Task with PIC',
        'end_date' => now()->addDays(7)->toDateString(),
        'pic' => [
            $employee->uid,
        ],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_tasks', [
        'name' => 'New Task with PIC',
        'project_board_id' => $this->project->boards->first()->id,
    ]);

    $task = \Modules\Production\Models\ProjectTask::where('name', 'New Task with PIC')->first();

    $this->assertDatabaseHas('project_task_deadlines', [
        'project_task_id' => $task->id,
        'employee_id' => $employee->id,
        'actual_finish_time' => null,
        'due_reason' => null,
        'custom_reason' => null,
    ]);

    $this->assertDatabaseHas('project_task_pics', [
        'project_task_id' => $task->id,
        'employee_id' => $employee->id,
    ]);
});