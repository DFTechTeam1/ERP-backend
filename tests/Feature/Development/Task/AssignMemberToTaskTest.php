<?php

use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Hrd\Models\Employee;
use App\Enums\Development\Project\Task\TaskStatus;
use App\Actions\Development\DefineTaskAction;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Assign new member to empty task with deadline with in progress status', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = DevelopmentProjectTask::factory()
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value
        ]);

    $newPic = Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.development.projects.tasks.members.store', $task->uid), [
        'removed' => [],
        'users' => [$newPic->uid]
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('development_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id
    ]);

    // check development project task deadlines table
    $this->assertDatabaseHas('development_project_task_deadlines', [
        'task_id' => $task->id,
        'deadline' => $deadline,
        'employee_id' => $newPic->id
    ]);

    $this->assertDatabaseMissing('development_project_task_deadlines', [
        'task_id' => $task->id,
        'deadline' => $deadline,
        'start_time' => null
    ]);

    $this->assertDatabaseHas('dev_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
        'is_until_finish' => 1
    ]);
});

it ('Assign pic to task that already have pic deadline', function() {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $currentPic = Employee::factory()->create();

    $task = DevelopmentProjectTask::factory()
        ->withPic(deadline: $deadline, employee: $currentPic)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value
        ]);

    $newPic = Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.development.projects.tasks.members.store', $task->uid), [
        'removed' => [],
        'users' => [$newPic->uid, $currentPic->uid]
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('development_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id
    ]);

    // make sure there no duplicate data on database
    $this->assertDatabaseCount('development_project_task_pics', 2);

    // count data
    $this->assertDatabaseCount('development_project_task_deadlines', 2);
    
    // check development project task deadlines table
    $this->assertDatabaseHas('development_project_task_deadlines', [
        'task_id' => $task->id,
        'deadline' => $deadline,
        'employee_id' => $newPic->id
    ]);

    $this->assertDatabaseMissing('development_project_task_deadlines', [
        'task_id' => $task->id,
        'deadline' => $deadline,
        'employee_id' => $newPic->id,
        'start_time' => null
    ]);

    // workstates table should be not empty
    $this->assertDatabaseHas('dev_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id
    ]);
    $this->assertDatabaseMissing('dev_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
        'started_at' => null
    ]);

    $this->assertDatabaseHas('dev_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
        'is_until_finish' => 1
    ]);
});

it('Remove current pic from task', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = DevelopmentProjectTask::factory()
        ->withPic($deadline)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value
        ]);

    $removeEmployee = Employee::select('uid', 'id')
        ->find($task->pics->first()->employee_id);

    $payload = [
        'removed' => [
            $removeEmployee->uid
        ],
        'users' => []
    ];

    $response = $this->postJson(route('api.development.projects.tasks.members.store', $task->uid), $payload);

    $response->assertStatus(201);

    // development project task deadlines should be empty
    $this->assertDatabaseCount('development_project_task_deadlines', 0);

    // development project task pics should be empty
    $this->assertDatabaseCount('development_project_task_pics', 0);

    // check task status, it should be draft
    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::Draft->value
    ]);

    $this->assertDatabaseHas('dev_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $removeEmployee->id,
        'is_until_finish' => 0
    ]);
});

it ('Assign pic to task without deadline', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);
        
   $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $task = DevelopmentProjectTask::factory()
        ->withPic()
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => TaskStatus::InProgress->value
        ]);

    $newPic = Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.development.projects.tasks.members.store', $task->uid), [
        'removed' => [],
        'users' => [$newPic->uid]
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('development_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id
    ]);

    $this->assertDatabaseMissing('development_project_task_deadlines', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
    ]);
    $this->assertDatabaseCount('development_project_task_deadlines', 0);

    $this->assertDatabaseHas('dev_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id,
        'is_until_finish' => 1
    ]);
});

it ('Remove user from task when already have workstate', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);
        
   $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $task = DevelopmentProjectTask::factory()
        ->withPic(null, true)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => TaskStatus::InProgress->value
        ]);
        
    $this->assertDatabaseHas('dev_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $task->pics->first()->employee_id
    ]);

    $employee = Employee::select('uid', 'id')
        ->find($task->pics->first()->employee_id);

    $newPic = Employee::factory()
        ->withUser()
        ->create();

    $payload = [
        'removed' => [
            $employee->uid
        ],
        'users' => [
            $newPic->uid
        ]
    ];

    $response = $this->postJson(route('api.development.projects.tasks.members.store', $task->uid), $payload);

    $response->assertStatus(201);

    $this->assertDatabaseMissing('development_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $employee->id
    ]);

    $this->assertDatabaseMissing('dev_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $employee->id
    ]);
    $this->assertDatabaseHas('dev_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $newPic->id
    ]);
    $this->assertDatabaseHas('dev_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $employee->id,
        'is_until_finish' => 0
    ]);
});

it ('Assign pic when task status is draft', function () {
     // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);
        
   $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $task = DevelopmentProjectTask::factory()
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => TaskStatus::Draft->value
        ]);

    $employee = Employee::factory()->create();

    $payload = [
        'removed' => [],
        'users' => [
            $employee->uid
        ]
    ];

    $response = $this->postJson(route('api.development.projects.tasks.members.store', $task->uid), $payload);

    $response->assertStatus(201);

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::WaitingApproval->value
    ]);
});