<?php

use App\Actions\Development\DefineTaskAction;
use App\Enums\Development\Project\ProjectStatus;
use App\Enums\Development\Project\Task\TaskStatus;
use Illuminate\Support\Facades\Storage;
use Modules\Development\Models\DevelopmentProjectTask;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Complete ongoing project with 7 active task', function () {
    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = \Modules\Development\Models\DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create([
            'status' => ProjectStatus::Active->value
        ]);

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = DevelopmentProjectTask::factory()
        ->withPic(withWorkState: true, deadline: $deadline)
        ->count(7)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value
        ]);

    $response = $this->getJson(route('api.development.projects.complete', $project->uid));

    logging('RESPONSE COMPLETE 1', $response->json());

    $response->assertStatus(400);
    expect($response->json()['message'])->toBe(__('notification.pleaseCompleteAllTasksManually'));

    $this->assertDatabaseHas('development_projects', [
        'id' => $project->id,
        'status' => ProjectStatus::Active->value
    ]);
});

it ('Complete ongoing project with 4 active task', function () {
    Storage::fake('public');

    // fake action
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = \Modules\Development\Models\DevelopmentProject::factory()
        ->withBoards()
        ->withPics()
        ->create([
            'status' => ProjectStatus::Active->value
        ]);

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = DevelopmentProjectTask::factory()
        ->withPic(withWorkState: true, deadline: $deadline)
        ->count(4)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value
        ]);

    $response = $this->getJson(route('api.development.projects.complete', $project->uid));

    $response->assertStatus(201);
    expect($response->json()['message'])->toBe(__('notification.projectHasBeenCompleted'));

    $this->assertDatabaseHas('development_projects', [
        'id' => $project->id,
        'status' => ProjectStatus::Completed->value
    ]);

    foreach ($task as $t) {
        $this->assertDatabaseHas('development_project_tasks', [
            'id' => $t->id,
            'status' => TaskStatus::Completed->value
        ]);

        $this->assertDatabaseMissing('development_project_task_pics', [
            'task_id' => $t->id,
        ]);
    }
});
