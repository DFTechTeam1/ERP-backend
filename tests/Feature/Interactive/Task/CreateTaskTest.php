<?php

use App\Enums\Interactive\InteractiveProjectStatus;
use App\Enums\Interactive\InteractiveTaskStatus;
use App\Jobs\NotifyInteractiveTaskAssigneeJob;
use Illuminate\Support\Facades\Bus;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Create project tasks with missing parameters', function () {
    $project = InteractiveProject::factory()->create([
        'status' => InteractiveProjectStatus::Draft->value,
    ]);

    $response = $this->postJson(route('api.production.interactives.storeTask', ['projectUid' => $project->uid]),
        []);

    $response->assertStatus(422);
});

it('Create project with only basic information', function () {
    $project = InteractiveProject::factory()->withBoards()->create([
        'status' => InteractiveProjectStatus::OnGoing->value,
    ]);

    $response = $this->postJson(route('api.production.interactives.storeTask', ['projectUid' => $project->uid]),
        [
            'name' => 'Task Name',
            'description' => 'Task Description',
            'board_id' => $project->boards->first()->id,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_tasks', [
        'name' => 'Task Name',
        'description' => 'Task Description',
        'intr_project_id' => $project->id,
        'status' => InteractiveTaskStatus::Draft->value,
    ]);

    $this->assertDatabaseEmpty('intr_project_task_deadlines');
    $this->assertDatabaseEmpty('intr_project_task_attachments');
    $this->assertDatabaseEmpty('intr_project_task_pic_histories');
});

it('Create task with deadline only without pic and references', function () {
    $project = InteractiveProject::factory()->withBoards()->create([
        'status' => InteractiveProjectStatus::OnGoing->value,
    ]);

    $deadline = now()->addDays(2)->format('Y-m-d H:i');

    $response = $this->postJson(route('api.production.interactives.storeTask', ['projectUid' => $project->uid]),
        [
            'name' => 'Task Name',
            'description' => 'Task Description',
            'end_date' => $deadline,
            'board_id' => $project->boards->first()->id,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_tasks', [
        'name' => 'Task Name',
        'description' => 'Task Description',
        'intr_project_id' => $project->id,
        'deadline' => $deadline,
    ]);

    $this->assertDatabaseEmpty('intr_project_task_deadlines');
    $this->assertDatabaseEmpty('intr_project_task_attachments');
    $this->assertDatabaseEmpty('intr_project_task_pic_histories');
});

it('Create task with deadline and pic', function () {
    Bus::fake();
    $project = InteractiveProject::factory()->withBoards()->create([
        'status' => InteractiveProjectStatus::OnGoing->value,
    ]);

    // create employee so we can assign as pic
    $employee = \Modules\Hrd\Models\Employee::factory()->withUser()->create();

    $deadline = now()->addDays(2)->format('Y-m-d H:i');

    $response = $this->postJson(route('api.production.interactives.storeTask', ['projectUid' => $project->uid]),
        [
            'name' => 'Task Name',
            'description' => 'Task Description',
            'end_date' => $deadline,
            'board_id' => $project->boards->first()->id,
            'pics' => [
                [
                    'employee_uid' => $employee->uid,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $currentTask = InteractiveProjectTask::where('intr_project_id', $project->id)
        ->where('name', 'Task Name')
        ->first();

    $this->assertDatabaseHas('intr_project_tasks', [
        'name' => 'Task Name',
        'description' => 'Task Description',
        'intr_project_id' => $project->id,
        'deadline' => $deadline,
    ]);

    $this->assertDatabaseEmpty('intr_project_task_attachments');

    $this->assertDatabaseHas('intr_project_task_deadlines', [
        'task_id' => $currentTask->id,
        'deadline' => $deadline,
        'employee_id' => $employee->id,
    ]);

    $this->assertDatabaseHas('intr_project_task_pic_histories', [
        'task_id' => $currentTask->id,
        'employee_id' => $employee->id,
        'is_until_finish' => 1,
    ]);

    Bus::assertDispatched(NotifyInteractiveTaskAssigneeJob::class);
});

it('Create task only with pic and no deadline', function () {
    Bus::fake();

    $project = InteractiveProject::factory()->withBoards()->create([
        'status' => InteractiveProjectStatus::OnGoing->value,
    ]);

    // create employee so we can assign as pic
    $employee = \Modules\Hrd\Models\Employee::factory()->withUser()->create();

    $deadline = now()->addDays(2)->format('Y-m-d H:i');

    $response = $this->postJson(route('api.production.interactives.storeTask', ['projectUid' => $project->uid]),
        [
            'name' => 'Task Name',
            'description' => 'Task Description',
            'end_date' => null,
            'board_id' => $project->boards->first()->id,
            'pics' => [
                [
                    'employee_uid' => $employee->uid,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $currentTask = InteractiveProjectTask::where('intr_project_id', $project->id)
        ->where('name', 'Task Name')
        ->first();

    $this->assertDatabaseHas('intr_project_tasks', [
        'name' => 'Task Name',
        'description' => 'Task Description',
        'intr_project_id' => $project->id,
        'deadline' => null,
    ]);

    $this->assertDatabaseHas('intr_project_task_pics', [
        'employee_id' => $employee->id,
        'task_id' => $currentTask->id,
    ]);

    $this->assertDatabaseHas('intr_project_task_pic_histories', [
        'task_id' => $currentTask->id,
        'employee_id' => $employee->id,
        'is_until_finish' => 1,
    ]);

    $this->assertDatabaseEmpty('intr_project_task_attachments');
    $this->assertDatabaseEmpty('intr_project_task_deadlines');

    Bus::assertDispatched(NotifyInteractiveTaskAssigneeJob::class);
});
