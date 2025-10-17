<?php

use App\Actions\DefineTaskAction;
use App\Actions\PartialTaskPermissionCheck;
use App\Actions\Project\DetailCache;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true
    );

    $this->actingAs($this->user);
});

it ('Approved task return success', function () {
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn(null);

    $currentEmployee = \Modules\Hrd\Models\Employee::find($this->user->employee_id);

    $project = \Modules\Production\Models\Project::factory()->create();
    $task = \Modules\Production\Models\ProjectTask::factory()
        ->withPics($currentEmployee)
        ->create([
            'project_id' => $project->id,
            'status' => \App\Enums\Production\TaskStatus::WaitingApproval->value,
            'is_approved' => false,
        ]);

    PartialTaskPermissionCheck::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($task);

    $response = $this->getJson(route('api.production.task.approve', [
        'projectUid' => $project->uid,
        'taskUid' => $task->uid,
    ]));

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_tasks', [
        'id' => $task->id,
        'status' => \App\Enums\Production\TaskStatus::OnProgress->value,
    ]);

    $this->assertDatabaseHas('project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $currentEmployee->id,
        'complete_at' => null,
        'first_finish_at' => null,
    ]);
});
