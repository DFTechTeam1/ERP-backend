<?php

use App\Actions\Project\DetailCache;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true,
        permissions: [
            'move_board',
            'edit_task_description',
            'add_task_description',
            'delete_task_description',
            'assign_modeller',
            'list_member'
        ]
    );

    $this->actingAs($this->user);

    $this->project = Project::factory()
        ->withBoards()
        ->create();
});

it ('Change project task deadline when deadline already set', function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $firstDeadline = now()->addDays(5)->format('Y-m-d H:i:s');
    $task = ProjectTask::factory()
        ->for($this->project, 'project')
        ->withPics()
        ->withDeadlines($this->user->id, $firstDeadline)
        ->create([
            'end_date' => $firstDeadline,
        ]);

    $newDeadline = now()->addDays(10)->format('Y-m-d H:i:s');

    $response = $this->postJson(
        route('api.production.task.update-deadline', [
            'projectUid' => $this->project->uid,
            'taskUid' => $task->uid
        ]),
        [
            'end_date' => $newDeadline,
            'reason_id' => 0,
            'type' => 'update',
            'custom_reason' => 'update dulu'
        ]
    );

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_task_deadlines', [
        'project_task_id' => $task->id,
        'deadline' => $newDeadline,
        'employee_id' => $task->pics->first()->employee_id,
        'actual_finish_time' => null,
        'due_reason' => '0',
        'custom_reason' => 'update dulu',
    ]);

    $this->assertDatabaseMissing('project_task_deadlines', [
        'project_task_id' => $task->id,
        'deadline' => $firstDeadline,
        'actual_finish_time' => null,
        'employee_id' => $task->pics->first()->employee_id,
    ]);
});

it ('Add deadline to current task with empty deadline data', function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $task = ProjectTask::factory()
        ->for($this->project, 'project')
        ->withPics()
        ->create();

    $this->assertDatabaseCount('project_task_deadlines', 0);

    $newDeadline = now()->addDays(10)->format('Y-m-d H:i:s');

    $response = $this->postJson(
        route('api.production.task.update-deadline', [
            'projectUid' => $this->project->uid,
            'taskUid' => $task->uid
        ]),
        [
            'end_date' => $newDeadline,
            'type' => 'add',
        ]
    );

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_task_deadlines', [
        'project_task_id' => $task->id,
        'deadline' => $newDeadline,
        'employee_id' => $task->pics->first()->employee_id,
        'actual_finish_time' => null,
        'due_reason' => null,
        'custom_reason' => null,
    ]);
});

it ('Update deadline with missing parameters', function () {
    $firstDeadline = now()->addDays(5)->format('Y-m-d H:i:s');
    $task = ProjectTask::factory()
        ->for($this->project, 'project')
        ->withPics()
        ->withDeadlines($this->user->id, $firstDeadline)
        ->create([
            'end_date' => $firstDeadline,
        ]);

    $newDeadline = now()->addDays(10)->format('Y-m-d H:i:s');
    $response = $this->postJson(
        route('api.production.task.update-deadline', [
            'projectUid' => $this->project->uid,
            'taskUid' => $task->uid
        ]),
        [
            'end_date' => $newDeadline,
            'type' => 'update',
        ]
    );

    $response->assertStatus(422);

    $response->assertJsonValidationErrors(['reason_id']);
});
