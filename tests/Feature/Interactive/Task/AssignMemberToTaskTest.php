<?php

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Interactive\InteractiveTaskStatus;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;

beforeEach(function () {
    $user = initAuthenticateUser(
        permissions: ['assign_interactive_task_member']
    );

    $this->actingAs($user);
});

it('Assign new member to empty task with deadline with in progress status', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = InteractiveProjectTask::factory()
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    $newPic = Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.production.interactives.tasks.members.store', $task->uid), [
        'removed' => [],
        'users' => [$newPic->uid],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
    ]);

    // check development project task deadlines table
    $this->assertDatabaseHas('intr_project_task_deadlines', [
        'task_id' => $task->id,
        'deadline' => $deadline,
        'employee_id' => $newPic->id,
    ]);

    $this->assertDatabaseMissing('intr_project_task_deadlines', [
        'task_id' => $task->id,
        'deadline' => $deadline,
        'start_time' => null,
    ]);

    $this->assertDatabaseHas('intr_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
        'is_until_finish' => 1,
    ]);
});

it('Assign pic to task that already have pic deadline', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $currentPic = Employee::factory()->create();

    $task = InteractiveProjectTask::factory()
        ->withPic(deadline: $deadline, employee: $currentPic)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    $newPic = Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.production.interactives.tasks.members.store', $task->uid), [
        'removed' => [],
        'users' => [$newPic->uid, $currentPic->uid],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
    ]);

    // make sure there no duplicate data on database
    $this->assertDatabaseCount('intr_project_task_pics', 2);

    // count data
    $this->assertDatabaseCount('intr_project_task_deadlines', 2);

    // check development project task deadlines table
    $this->assertDatabaseHas('intr_project_task_deadlines', [
        'task_id' => $task->id,
        'deadline' => $deadline,
        'employee_id' => $newPic->id,
    ]);

    $this->assertDatabaseMissing('intr_project_task_deadlines', [
        'task_id' => $task->id,
        'deadline' => $deadline,
        'employee_id' => $newPic->id,
        'start_time' => null,
    ]);

    // workstates table should be not empty
    $this->assertDatabaseHas('intr_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
    ]);
    $this->assertDatabaseMissing('intr_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
        'started_at' => null,
    ]);

    $this->assertDatabaseHas('intr_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
        'is_until_finish' => 1,
    ]);
});

it('Remove current pic from task', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = InteractiveProjectTask::factory()
        ->withPic($deadline)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    $removeEmployee = Employee::select('uid', 'id')
        ->find($task->pics->first()->employee_id);

    $payload = [
        'removed' => [
            $removeEmployee->uid,
        ],
        'users' => [],
    ];

    $response = $this->postJson(route('api.production.interactives.tasks.members.store', $task->uid), $payload);

    $response->assertStatus(201);

    // development project task deadlines should be empty
    $this->assertDatabaseCount('intr_project_task_deadlines', 0);

    // development project task pics should be empty
    $this->assertDatabaseCount('intr_project_task_pics', 0);

    // check task status, it should be draft
    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::Draft->value,
    ]);

    $this->assertDatabaseHas('intr_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $removeEmployee->id,
        'is_until_finish' => 0,
    ]);
});

it('Assign pic to task without deadline', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $task = InteractiveProjectTask::factory()
        ->withPic()
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    $newPic = Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.production.interactives.tasks.members.store', $task->uid), [
        'removed' => [],
        'users' => [$newPic->uid],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
    ]);

    $this->assertDatabaseMissing('intr_project_task_deadlines', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
    ]);
    $this->assertDatabaseCount('intr_project_task_deadlines', 0);

    $this->assertDatabaseHas('intr_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
        'is_until_finish' => 1,
    ]);
});

it('Remove user from task when already have workstate', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $task = InteractiveProjectTask::factory()
        ->withPic(null, true)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    $this->assertDatabaseHas('intr_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $task->pics->first()->employee_id,
    ]);

    $employee = Employee::select('uid', 'id')
        ->find($task->pics->first()->employee_id);

    $newPic = Employee::factory()
        ->withUser()
        ->create();

    $payload = [
        'removed' => [
            $employee->uid,
        ],
        'users' => [
            $newPic->uid,
        ],
    ];

    $response = $this->postJson(route('api.production.interactives.tasks.members.store', $task->uid), $payload);

    $response->assertStatus(201);

    $this->assertDatabaseMissing('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $employee->id,
    ]);

    $this->assertDatabaseMissing('intr_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $employee->id,
    ]);
    $this->assertDatabaseHas('intr_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
    ]);
    $this->assertDatabaseHas('intr_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $employee->id,
        'is_until_finish' => 0,
    ]);
});

it('Assign pic when task status is draft', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $task = InteractiveProjectTask::factory()
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => InteractiveTaskStatus::Draft->value,
        ]);

    $employee = Employee::factory()->create();

    $payload = [
        'removed' => [],
        'users' => [
            $employee->uid,
        ],
    ];

    $response = $this->postJson(route('api.production.interactives.tasks.members.store', $task->uid), $payload);

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::WaitingApproval->value,
    ]);
});
