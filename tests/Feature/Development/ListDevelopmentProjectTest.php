<?php

use App\Enums\Development\Project\Task\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Hrd\Models\Employee;

it('Get list project with root role', function () {
    $user = initAuthenticateUser([], BaseRole::Root->value);
    $this->actingAs($user);

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $response = $this->getJson(route('api.development.projects.index'));
    
    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data' => [
            'paginated' => [
                '*' => [
                    'id',
                    'uid',
                    'name',
                    'description',
                    'status',
                    'status_text',
                    'status_color',
                    'project_date',
                    'project_date_text',
                    'created_by',
                    'pic_name',
                    'total_task',
                    'pics' => [
                        '*' => [
                            'id',
                            'nickname'
                        ]
                    ],
                    'pic_uids'
                ]
            ],
            'totalData'
        ]
    ]);

    expect($response->json()['data']['totalData'])->toBe(1);
});

it ('Get list project as project manager who have assigned project', function () {
    $pic = Employee::factory()
        ->withUser()
        ->create();

    $employee = Employee::find($pic->id);

    $userData = User::find($employee->user_id);
    
    $user = initAuthenticateUser(roleName: BaseRole::ProjectManager->value, user: $userData);
    $this->actingAs($user);

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics(employee: $pic)
        ->create();

    $response = $this->getJson(route('api.development.projects.index'));
    
    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data' => [
            'paginated' => [
                '*' => [
                    'id',
                    'uid',
                    'name',
                    'description',
                    'status',
                    'status_text',
                    'status_color',
                    'project_date',
                    'project_date_text',
                    'created_by',
                    'pic_name',
                    'total_task',
                    'pics' => [
                        '*' => [
                            'id',
                            'nickname'
                        ]
                    ],
                    'pic_uids'
                ]
            ],
            'totalData'
        ]
    ]);

    expect($response->json()['data']['totalData'])->toBe(1);

    // check id
    expect($response->json()['data']['paginated'][0]['id'])->toBe($project->id);
});

it ("Get list project acting as Project manager who do not have project init", function () {
    $pic = Employee::factory()
        ->withUser()
        ->create();

    $employee = Employee::find($pic->id);

    $userData = User::find($employee->user_id);
    
    $user = initAuthenticateUser(roleName: BaseRole::ProjectManager->value, user: $userData);
    $this->actingAs($user);

    DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $response = $this->getJson(route('api.development.projects.index'));
    
    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data' => [
            'paginated',
            'totalData'
        ]
    ]);

    expect($response->json()['data']['totalData'])->toBe(0);
});

it ('Get list project acting as production employee who have a assigned task', function () {
    $pic = Employee::factory()
        ->withUser()
        ->create();

    $employee = Employee::find($pic->id);

    $userData = User::find($employee->user_id);
    
    $user = initAuthenticateUser(roleName: BaseRole::Production->value, user: $userData);
    $this->actingAs($user);

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d');

    $task = DevelopmentProjectTask::factory()
        ->withPic(employee: $employee)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value
        ]);

    $response = $this->getJson(route('api.development.projects.index'));

    $response->assertJsonStructure([
        'message',
        'data' => [
            'paginated' => [
                '*' => [
                    'id',
                    'uid',
                    'name',
                    'description',
                    'status',
                    'status_text',
                    'status_color',
                    'project_date',
                    'project_date_text',
                    'created_by',
                    'pic_name',
                    'total_task',
                    'pics' => [
                        '*' => [
                            'id',
                            'nickname'
                        ]
                    ],
                    'pic_uids'
                ]
            ],
            'totalData'
        ]
    ]);

    expect($response->json()['data']['totalData'])->toBe(1);

    // check id
    expect($response->json()['data']['paginated'][0]['id'])->toBe($project->id);
});

it ('Get list project as production and do not have any task', function () {
    $pic = Employee::factory()
        ->withUser()
        ->create();

    $employee = Employee::find($pic->id);

    $userData = User::find($employee->user_id);
    
    $user = initAuthenticateUser(roleName: BaseRole::Production->value, user: $userData);
    $this->actingAs($user);

    $project = DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $deadline = now()->addDays(7)->format('Y-m-d');

    $task = DevelopmentProjectTask::factory()
        ->withPic()
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value
        ]);

    $response = $this->getJson(route('api.development.projects.index'));

    $response->assertJsonStructure([
        'message',
        'data' => [
            'paginated',
            'totalData'
        ]
    ]);

    expect($response->json()['data']['totalData'])->toBe(0);
});