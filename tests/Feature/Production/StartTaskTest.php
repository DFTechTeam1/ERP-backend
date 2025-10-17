<?php

use App\Actions\Project\DetailCache;
use Modules\Hrd\Models\Employee;
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

it ('Start task after hold return success', function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $employee = Employee::find($this->user->employee_id);

    $task = ProjectTask::factory()
        ->for($this->project, 'project')
        ->for($this->project->boards->first(), 'board')
        ->withPics(employee: $employee, withWorkState: true, withHoldState: true)
        ->create([
            'status' => \App\Enums\Development\Project\Task\TaskStatus::OnHold->value,
        ]);

    $response = $this->getJson(route('api.production.task.state', [
        'projectUid' => $this->project->uid,
        'taskUid' => $task->uid,
    ]));

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $employee->id,
    ]);

    $this->assertDatabaseHas('project_tasks', [
        'id' => $task->id,
        'status' => \App\Enums\Production\TaskStatus::OnProgress->value,
    ]);

    $this->assertDatabaseMissing('project_task_pic_holdstates', [
        'task_id' => $task->id,
        'employee_id' => $employee->id,
        'work_state_id' => $task->workStates->first()->id,
        'unholded_at' => null,
        'holded_at' => null,
    ]);

    $this->assertDatabaseCount('project_task_pic_holdstates', 1);
});