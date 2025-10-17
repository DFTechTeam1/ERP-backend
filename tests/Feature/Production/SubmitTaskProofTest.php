<?php

use Modules\Production\Models\Project;

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

it ('Submit task proof of work return success', function () {
    $employee = \Modules\Hrd\Models\Employee::find($this->user->employee_id);

    $task = \Modules\Production\Models\ProjectTask::factory()
        ->for($this->project, 'project')
        ->for($this->project->boards->first(), 'board')
        ->withPics(employee: $employee, withWorkState: true)
        ->create([
            'status' => \App\Enums\Production\TaskStatus::OnProgress->value,
        ]);

    $response = $this->postJson(route('api.production.task.proof.store', [
        'projectUid' => $this->project->uid,
        'taskUid' => $task->uid,
    ]), [
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_task_proof_of_works', [
        'task_id' => $task->id,
        'submitted_by' => $employee->id,
        'note' => 'This is proof of work note',
    ]);
});


