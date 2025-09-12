<?php

use App\Enums\Development\Project\ProjectStatus;
use App\Enums\Development\Project\Task\TaskStatus;
use Illuminate\Support\Facades\Bus;
use Modules\Development\Jobs\NotifyTaskAssigneeJob;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Create project tasks with missing parameters', function () {
    $project = \Modules\Development\Models\DevelopmentProject::factory()->create([
        'status' => ProjectStatus::Active->value,
    ]);

    $response = $this->postJson(route('api.development.projects.tasks.store', ['projectUid' => $project->uid]),
        []);

    $response->assertStatus(422);
});

it('Create project with only basic information', function () {
    $project = \Modules\Development\Models\DevelopmentProject::factory()->withBoards()->create([
        'status' => ProjectStatus::Active->value,
    ]);

    $response = $this->postJson(route('api.development.projects.tasks.store', ['projectUid' => $project->uid]),
        [
            'name' => 'Task Name',
            'description' => 'Task Description',
            'board_id' => $project->boards->first()->id,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('development_project_tasks', [
        'name' => 'Task Name',
        'description' => 'Task Description',
        'development_project_id' => $project->id,
        'status' => TaskStatus::Draft->value,
    ]);

    $this->assertDatabaseEmpty('development_project_task_deadlines');
    $this->assertDatabaseEmpty('development_project_task_attachments');
    $this->assertDatabaseEmpty('dev_project_task_pic_histories');
});

it('Create task with deadline only without pic and references', function () {
    $project = \Modules\Development\Models\DevelopmentProject::factory()->withBoards()->create([
        'status' => ProjectStatus::Active->value,
    ]);

    $deadline = now()->addDays(2)->format('Y-m-d H:i');

    $response = $this->postJson(route('api.development.projects.tasks.store', ['projectUid' => $project->uid]),
        [
            'name' => 'Task Name',
            'description' => 'Task Description',
            'end_date' => $deadline,
            'board_id' => $project->boards->first()->id,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('development_project_tasks', [
        'name' => 'Task Name',
        'description' => 'Task Description',
        'development_project_id' => $project->id,
        'deadline' => $deadline,
    ]);

    $this->assertDatabaseEmpty('development_project_task_deadlines');
    $this->assertDatabaseEmpty('development_project_task_attachments');
    $this->assertDatabaseEmpty('dev_project_task_pic_histories');
});

it('Create task with deadline and pic', function () {
    Bus::fake();
    $project = \Modules\Development\Models\DevelopmentProject::factory()->withBoards()->create([
        'status' => ProjectStatus::Active->value,
    ]);

    // create employee so we can assign as pic
    $employee = \Modules\Hrd\Models\Employee::factory()->withUser()->create();

    $deadline = now()->addDays(2)->format('Y-m-d H:i');

    $response = $this->postJson(route('api.development.projects.tasks.store', ['projectUid' => $project->uid]),
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

    $currentTask = \Modules\Development\Models\DevelopmentProjectTask::where('development_project_id', $project->id)
        ->where('name', 'Task Name')
        ->first();

    $this->assertDatabaseHas('development_project_tasks', [
        'name' => 'Task Name',
        'description' => 'Task Description',
        'development_project_id' => $project->id,
        'deadline' => $deadline,
    ]);

    $this->assertDatabaseEmpty('development_project_task_attachments');

    $this->assertDatabaseHas('development_project_task_deadlines', [
        'task_id' => $currentTask->id,
        'deadline' => $deadline,
        'employee_id' => $employee->id,
    ]);

    $this->assertDatabaseHas('dev_project_task_pic_histories', [
        'task_id' => $currentTask->id,
        'employee_id' => $employee->id,
        'is_until_finish' => 1,
    ]);

    Bus::assertDispatched(NotifyTaskAssigneeJob::class);
});

it('Create task only with pic and no deadline', function () {
    Bus::fake();

    $project = \Modules\Development\Models\DevelopmentProject::factory()->withBoards()->create([
        'status' => ProjectStatus::Active->value,
    ]);

    // create employee so we can assign as pic
    $employee = \Modules\Hrd\Models\Employee::factory()->withUser()->create();

    $deadline = now()->addDays(2)->format('Y-m-d H:i');

    $response = $this->postJson(route('api.development.projects.tasks.store', ['projectUid' => $project->uid]),
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

    $currentTask = \Modules\Development\Models\DevelopmentProjectTask::where('development_project_id', $project->id)
        ->where('name', 'Task Name')
        ->first();

    $this->assertDatabaseHas('development_project_tasks', [
        'name' => 'Task Name',
        'description' => 'Task Description',
        'development_project_id' => $project->id,
        'deadline' => null,
    ]);

    $this->assertDatabaseHas('development_project_task_pics', [
        'employee_id' => $employee->id,
        'task_id' => $currentTask->id,
    ]);

    $this->assertDatabaseHas('dev_project_task_pic_histories', [
        'task_id' => $currentTask->id,
        'employee_id' => $employee->id,
        'is_until_finish' => 1,
    ]);

    $this->assertDatabaseEmpty('development_project_task_attachments');
    $this->assertDatabaseEmpty('development_project_task_deadlines');

    Bus::assertDispatched(NotifyTaskAssigneeJob::class);
});
