<?php

use App\Actions\DefineDetailProjectPermission;
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
            'list_member',
            'list_entertainment_member'
        ]
    );

    $this->actingAs($this->user);

    // create project pic employee
    $this->projectPic = \Modules\Hrd\Models\Employee::find($this->user->employee_id);

    $this->project = Project::factory()
        ->withBoards()
        ->withPics(employee: $this->projectPic)
        ->create();
});

it ('Assign member to current task with empty pic and have deadline', function () {
    DefineDetailProjectPermission::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $deadline = now()->addDays(10)->format('Y-m-d H:i:s');
    $task = \Modules\Production\Models\ProjectTask::factory()
        ->for($this->project, 'project')
        ->create([
            'end_date' => $deadline
        ]);

    $employee = \Modules\Hrd\Models\Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.production.task.assign-member', [
        'taskId' => $task->uid,
        'projectId' => $this->project->uid,
    ]), [
        'users' => [
            $employee->uid
        ],
        'removed' => []
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_task_pics', [
        'project_task_id' => $task->id,
        'employee_id' => $employee->id,
    ]);

    $this->assertDatabaseHas('project_task_deadlines', [
        'project_task_id' => $task->id,
        'employee_id' => $employee->id,
        'deadline' => $deadline,
        'is_first_deadline' => true,
        'actual_finish_time' => null,
        'due_reason' => null,
        'custom_reason' => null,
    ]);
});

it ('Assign new member and remove old pic in current task with deadlines', function () {
    DefineDetailProjectPermission::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $firstPic = Employee::factory()
        ->withUser()
        ->create();

    $deadline = now()->addDays(10)->format('Y-m-d H:i:s');
    $task = \Modules\Production\Models\ProjectTask::factory()
        ->for($this->project, 'project')
        ->withPics(employee: $firstPic, withWorkState: true)
        ->withDeadlines(userId: $this->user->id, deadline: $deadline)
        ->create([
            'end_date' => $deadline
        ]);

    $this->assertDatabaseHas('project_task_pics', [
        'project_task_id' => $task->id,
        'employee_id' => $firstPic->id,
    ]);

    $newPic = Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.production.task.assign-member', [
        'taskId' => $task->uid,
        'projectId' => $this->project->uid,
    ]), [
        'users' => [
            $newPic->uid
        ],
        'removed' => [
            $firstPic->uid
        ]
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_task_pics', [
        'project_task_id' => $task->id,
        'employee_id' => $newPic->id,
    ]);

    $this->assertDatabaseMissing('project_task_pics', [
        'project_task_id' => $task->id,
        'employee_id' => $firstPic->id,
    ]);
});