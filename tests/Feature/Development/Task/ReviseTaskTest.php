<?php

use App\Actions\Development\DefineTaskAction;
use App\Enums\Development\Project\Task\TaskStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Development\Models\DevelopmentProjectTaskPic;
use Modules\Hrd\Models\Employee;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it('Revise task', function () {
    Storage::fake('public');

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

    $boss = Employee::factory()->create();

    $worker = Employee::factory()
        ->withUser()
        ->create([
            'boss_id' => $boss->id,
        ]);

    $task = DevelopmentProjectTask::factory()
        ->withPic(employee: $worker)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value,
        ]);

    // manually update the status
    DevelopmentProjectTask::where('id', $task->id)
        ->update([
            'status' => TaskStatus::CheckByPm->value,
        ]);

    // manually remove pic
    DevelopmentProjectTaskPic::where('task_id', $task->id)
        ->delete();

    // revise
    $response = $this->postJson(route('api.development.projects.tasks.revised', $task->uid), [
        'reason' => 'revisi',
        'images' => [
            [
                'image' => UploadedFile::fake()->image('test.jpg'),
            ],
        ],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseMissing('development_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $boss->id,
    ]);

    $this->assertDatabaseHas('development_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::Revise->value,
    ]);

    $this->assertDatabaseHas('dev_project_task_revises', [
        'task_id' => $task->id,
        'reason' => 'revisi',
    ]);
});
