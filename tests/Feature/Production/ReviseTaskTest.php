<?php

use App\Actions\Project\DetailCache;
use Illuminate\Http\UploadedFile;
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

    // create project pic employee
    $this->projectPic = \Modules\Hrd\Models\Employee::factory()->create();

    $this->project = Project::factory()
        ->withBoards()
        ->withPics(employee: $this->projectPic)
        ->create();
});

it ('Revise task return success', function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $employee = \Modules\Hrd\Models\Employee::find($this->user->employee_id);
    
    // create task
    $task = \Modules\Production\Models\ProjectTask::factory()
        ->for($this->project, 'project')
        ->for($this->project->boards->first(), 'board')
        ->withPics(employee: $employee, withWorkState: true)
        ->create([
            'status' => \App\Enums\Production\TaskStatus::OnProgress->value,
        ]);

    $responseSubmit = $this->postJson(route('api.production.task.proof.store', [
        'projectId' => $this->project->uid,
        'taskId' => $task->uid,
    ]), [
        'nas_link' => 'http://123123',
        'preview' => [
            UploadedFile::fake()->image('test-image.jpg'),
        ]
    ]);

    $responseSubmit->assertStatus(201);

    $response = $this->postJson(route('api.production.task.revise', [
        'projectUid' => $this->project->uid,
        'taskUid' => $task->uid,
    ]), [
        'reason' => 'Need to revise the work',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseMissing('project_task_pic_approvalstates', [
        'task_id' => $task->id,
        'pic_id' => $this->projectPic->id,
        'project_id' => $this->project->id,
        'started_at' => null,
        'approved_at' => null,
    ]);

    $this->assertDatabaseHas('project_tasks', [
        'id' => $task->id,
        'status' => \App\Enums\Production\TaskStatus::Revise->value,
    ]);

    $this->assertDatabaseHas('project_task_pics', [
        'project_task_id' => $task->id,
        'employee_id' => $employee->id,
    ]);

    $this->assertDatabaseHas('project_task_pic_revisestates', [
        'task_id' => $task->id,
        'employee_id' => $employee->id,
        'finish_at' => null,
    ]);
});
