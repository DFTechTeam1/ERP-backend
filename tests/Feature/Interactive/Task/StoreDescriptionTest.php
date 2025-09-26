<?php

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Interactive\InteractiveTaskStatus;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it('Store new description to task', function () {
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker, withWorkState: true)
        ->withHoldState()
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => InteractiveTaskStatus::InProgress->value,
            'description' => null,
        ]);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'description' => null,
    ]);

    $response = $this->postJson(route('api.production.interactives.tasks.description.store', $task->uid), [
        'description' => 'new description',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'description' => 'new description',
    ]);
});

it('Update current description', function () {
    DefineTaskAction::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn([]);

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics()
        ->create();

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker, withWorkState: true)
        ->withHoldState()
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => null,
            'status' => InteractiveTaskStatus::InProgress->value,
            'description' => 'old description',
        ]);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'description' => 'old description',
    ]);

    $response = $this->postJson(route('api.production.interactives.tasks.description.store', $task->uid), [
        'description' => 'updated description',
    ]);

    $response->assertStatus(201);

    $this->assertdatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'description' => 'updated description',
    ]);
});
