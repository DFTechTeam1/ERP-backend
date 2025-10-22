<?php

use App\Actions\Project\DetailCache;
use App\Enums\Production\TaskStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it ('Submit task proof of work return success', function () {
    Storage::fake('public');

    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $employee = \Modules\Hrd\Models\Employee::find($this->user->employee_id);
    $deadline = now()->addDays(3)->format('Y-m-d H:i:s');
    
    $task = \Modules\Production\Models\ProjectTask::factory()
        ->for($this->project, 'project')
        ->for($this->project->boards->first(), 'board')
        ->withPics(employee: $employee, withWorkState: true)
        ->withDeadlines(userId: $this->user->id, deadline: $deadline)
        ->create([
            'status' => \App\Enums\Production\TaskStatus::OnProgress->value,
            'end_date' => $deadline
        ]);

    $response = $this->postJson(route('api.production.task.proof.store', [
        'projectId' => $this->project->uid,
        'taskId' => $task->uid,
    ]), [
        'nas_link' => 'http://123123',
        'preview' => [
            UploadedFile::fake()->image('test-image.jpg'),
        ]
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseCount('project_task_deadlines', 1);
    $this->assertDatabaseHas('project_task_deadlines', [
        'project_task_id' => $task->id,
        'employee_id' => $employee->id,
        'deadline' => $deadline,
    ]);
    $this->assertDatabaseMissing('project_task_deadlines', [
        'project_task_id' => $task->id,
        'employee_id' => $employee->id,
        'actual_finish_time' => null,
    ]);

    $this->assertDatabaseHas('project_task_proof_of_works', [
        'project_task_id' => $task->id,
        'project_id' => $this->project->id,
        'nas_link' => 'http://123123',
    ]);

    $this->assertDatabaseHas('project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::CheckByPm->value
    ]);

    $this->assertDatabaseHas('project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $employee->id
    ]);

    $this->assertDatabaseMissing('project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $employee->id,
        'first_finish_at' => null,
    ]);

    $this->assertDatabaseCount('project_task_pic_workstates', 1);

    $this->assertDatabaseHas('project_task_pic_approvalstates', [
        'task_id' => $task->id,
        'pic_id' => $this->projectPic->id,
        'project_id' => $this->project->id,
        'work_state_id' => $task->workStates->first()->id,
    ]);

    $this->assertDatabaseCount('project_task_pic_approvalstates', 1);
});


