<?php

use App\Actions\Project\DetailCache;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true,
        permissions: ['create_pool_task']
    );

    $this->actingAs($this->user);

    $this->project = Project::factory()
        ->withBoards()
        ->create();

    $this->boardId = $this->project->boards->first()->id;
});

it('returns 403 when user lacks create_pool_task permission', function () {
    $user = initAuthenticateUser(withEmployee: true, permissions: []);
    $this->actingAs($user);

    $response = $this->postJson(route('api.production.storePoolTask', [
        'boardId' => $this->boardId,
    ]), [
        'name' => 'Pool Task',
    ]);

    $response->assertForbidden();
});

it('fails validation when name is missing', function () {
    $response = $this->postJson(route('api.production.storePoolTask', [
        'boardId' => $this->boardId,
    ]), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
});

it('creates a pool task with only name', function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $response = $this->postJson(route('api.production.storePoolTask', [
        'boardId' => $this->boardId,
    ]), [
        'name' => 'My Pool Task',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('project_tasks', [
        'name' => 'My Pool Task',
        'project_board_id' => $this->boardId,
        'is_pool_task' => true,
        'status' => null,
    ]);

    $task = ProjectTask::where('name', 'My Pool Task')->first();

    $this->assertDatabaseMissing('project_task_pics', [
        'project_task_id' => $task->id,
    ]);
});

it('creates a pool task with optional fields', function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $endDate = now()->addDays(10)->toDateString();

    $response = $this->postJson(route('api.production.storePoolTask', [
        'boardId' => $this->boardId,
    ]), [
        'name' => 'Pool Task With Details',
        'end_date' => $endDate,
        'description' => 'This is a detailed description',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('project_tasks', [
        'name' => 'Pool Task With Details',
        'project_board_id' => $this->boardId,
        'is_pool_task' => true,
        'description' => 'This is a detailed description',
    ]);
});

it('fails validation with an invalid date format', function () {
    $response = $this->postJson(route('api.production.storePoolTask', [
        'boardId' => $this->boardId,
    ]), [
        'name' => 'Pool Task',
        'end_date' => 'not-a-date',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['end_date']);
});
