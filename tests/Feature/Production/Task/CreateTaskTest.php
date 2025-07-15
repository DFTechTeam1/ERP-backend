<?php

use App\Actions\DefineTaskAction;
use App\Actions\Project\DetailCache;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectBoard;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // create permission
    $permissionNames = ['move_board', 'edit_task_description', 'add_task_description', 'delete_task_description'];
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

test('Create task only with name', function () use ($defaultBoards) {
    $mockDetailCache = Mockery::mock(DetailCache::class);
    $mockDetailCache->shouldReceive('handle')
    ->withAnyArgs()
    ->andReturn([]);

    $service = createProjectService(detailCacheAction: $mockDetailCache);
    
    $project = Project::factory()->create();

    foreach ($defaultBoards as $board) {
        ProjectBoard::create([
            'project_id' => $project->id,
            'name' => $board['name'],
            'sort' => $board['sort'],
            'based_board_id' => $board['based_board_id'],
        ]);
    }

    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $payload = [
        'name' => 'Task name'
    ];

    $response = $service->storeTask(data: $payload, boardId: $defaultBoards[0]['id']);

    expect($response['error'])->toBeFalse();
    expect($response['message'])->toBe(__('global.taskCreated'));

    // check database
    $this->assertDatabaseHas('project_tasks', [
        'project_id' => $project->id,
        'name' => 'Task name'
    ]);
    $this->assertDatabaseCount('project_tasks', 1);

    $this->assertDatabaseCount('project_task_pics', 0);
});
