<?php

use App\Enums\Production\ProjectStatus;
use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\DeadlineChangeReason;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectBoard;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskDeadline;
use Modules\Production\Models\ProjectTaskPic;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // create permission
    $permissionNames = [
        'move_board',
        'edit_task_description',
        'add_task_description',
        'delete_task_description',
        'assign_modeller',
        'list_member',
        'list_entertainment_member',
        'add_team_member',
        'add_references',
        'list_request_song',
        'create_request_song',
        'distribute_request_song',
        'add_showreels',
        'list_task',
        'add_task',
        'delete_task',
        'complete_project'
    ];
    foreach ($permissionNames as $name) {
        Permission::create(['name' => $name, 'guard_name' => 'sanctum']);
    }
    $user = initAuthenticateUser(permissions: $permissionNames);

    $this->actingAs($user);
});

$defaultBoards = [
    [
        'name' => 'Asset 3D',
        'sort' => 0,
        'id' => 1,
        'based_board_id' => 1
    ],
    [
        'name' => 'Compositing',
        'sort' => 1,
        'id' => 2,
        'based_board_id' => 2
    ],
    [
        'name' => 'Animating',
        'sort' => 2,
        'id' => 3,
        'based_board_id' => 3
    ],
    [
        'name' => 'Finalize',
        'sort' => 3,
        'id' => 4,
        'based_board_id' => 4
    ],
];

test("Update deadline on task have pic", function () use ($defaultBoards) {
    $taskDate = now()->addDays(5)->format('Y-m-d');
    $taskUpdateDate = now()->addDays(10)->format('Y-m-d H:i');

    $reason = DeadlineChangeReason::factory()
        ->count(2)
        ->create();

    $employee = Employee::factory()->create();
    $user = User::factory()->create([
        'employee_id' => $employee->Id
    ]);
    Employee::where('id', $employee->id)
        ->update([
            'user_id' => $user->id
        ]);

    $project = Project::factory()->create([
        'status' => ProjectStatus::OnGoing->value
    ]);

    foreach ($defaultBoards as $board) {
        ProjectBoard::create([
            'project_id' => $project->id,
            'name' => $board['name'],
            'sort' => $board['sort'],
            'based_board_id' => $board['based_board_id'],
        ]);
    }

    // create task
    $task = ProjectTask::factory()
        ->has(ProjectTaskPic::factory()->state([
            'employee_id' => $employee->id
        ]), 'pics')
        ->has(ProjectTaskDeadline::factory()->state([
            'employee_id' => $employee->id,
        ]), 'deadlines')
        ->create([
            'project_id' => $project->id,
            'project_board_id' => $project->boards[0]->id,
            'end_date' => now()->format('Y-m-d')
        ]);

    $payload = [
        'task_id' => $task->uid,
        'due_date' => $taskUpdateDate,
        'reason_id' => $reason[0]->id,
        'reason_custom' => null
    ];

    $service = createProjectService();

    $response = $service->updateDeadline(data: $payload, projectUid: $project->uid);

    expect($response)->toHaveKeys(['error', 'message']);
    expect($response['error'])->toBeFalse();

    $this->assertDatabaseCount('project_task_deadlines', 2);
    $this->assertDatabaseHas('project_task_deadlines', [
        'employee_id' => $employee->id,
        'due_reason' => $reason[0]->id,
        'project_task_id' => $task->id
    ]);
});

test('Update task deadline with no pic', function () use ($defaultBoards) {
    $taskUpdateDate = now()->addDays(10)->format('Y-m-d H:i');

    $reason = DeadlineChangeReason::factory()
        ->count(2)
        ->create();

    $employee = Employee::factory()->create();
    $user = User::factory()->create([
        'employee_id' => $employee->Id
    ]);
    Employee::where('id', $employee->id)
        ->update([
            'user_id' => $user->id
        ]);

    $project = Project::factory()->create([
        'status' => ProjectStatus::OnGoing->value
    ]);

    foreach ($defaultBoards as $board) {
        ProjectBoard::create([
            'project_id' => $project->id,
            'name' => $board['name'],
            'sort' => $board['sort'],
            'based_board_id' => $board['based_board_id'],
        ]);
    }

    // create task
    $task = ProjectTask::factory()
        ->create([
            'project_id' => $project->id,
            'project_board_id' => $project->boards[0]->id,
            'end_date' => now()->format('Y-m-d')
        ]);

    $payload = [
        'task_id' => $task->uid,
        'due_date' => $taskUpdateDate,
        'reason_id' => $reason[0]->id,
        'reason_custom' => null
    ];

    $service = createProjectService();

    $response = $service->updateDeadline(data: $payload, projectUid: $project->uid);

    expect($response)->toHaveKeys(['error', 'message']);
    expect($response['error'])->toBeFalse();

    $this->assertDatabaseCount('project_task_deadlines', 0);
    $this->assertDatabaseMissing('project_task_deadlines', [
        'employee_id' => $employee->id,
        'project_task_id' => $task->id
    ]);
    $this->assertDatabaseCount('project_task_pics', 0);
    $this->assertDatabaseHas('project_tasks', [
        'end_date' => date('Y-m-d H:i:s', strtotime($taskUpdateDate)),
        'id' => $task->id
    ]);
});
