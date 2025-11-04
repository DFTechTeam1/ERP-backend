<?php

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Interactive\InteractiveTaskStatus;
use App\Enums\System\BaseRole;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        permissions: ['approve_interactive_task']
    );

    $this->actingAs($this->user);
});

it('Approve task return success', function () {
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

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::WaitingApproval->value,
        ]);

    $this->assertDatabaseCount('intr_project_task_pic_workstates', 0);

    // approve task
    $response = $this->get(route('api.production.interactives.tasks.approved', $task->uid));

    $response->assertStatus(201);

    $this->assertDatabaseHas('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    // check workstates
    $this->assertDatabaseHas('intr_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    // check task status
    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::InProgress->value,
    ]);

    // check response format
    $response->assertJsonStructure([
        'message',
        'data',
    ]);
});

it('Approve task when task status is Check By Pm', function () {
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

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::CheckByPm->value,
        ]);

    // approve task
    $response = $this->get(route('api.production.interactives.tasks.approved', $task->uid));

    $response->assertStatus(400);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::CheckByPm->value,
    ]);

    expect($response->json('message'))->toBe(__('notification.taskCannotBeApproved'));
});

it('Approve task when task already In Progress', function () {
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

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    // approve task
    $response = $this->get(route('api.production.interactives.tasks.approved', $task->uid));

    $response->assertStatus(400);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::InProgress->value,
    ]);

    expect($response->json('message'))->toBe(__('notification.taskAlreadyApproved'));
});

it('Approve task when user is not allowed', function () {
    $anotherUser = initAuthenticateUser(roleName: BaseRole::Hrd->value);

    $this->actingAs($anotherUser);

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

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::WaitingApproval->value,
        ]);

    // approve task
    $response = $this->get(route('api.production.interactives.tasks.approved', $task->uid));

    $response->assertStatus(400);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::WaitingApproval->value,
    ]);

    expect($response->json('message'))->toBe("You don't have permission to access this resource.");
});

it('Approve task when task do not have pic', function () {
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

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = InteractiveProjectTask::factory()
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::WaitingApproval->value,
        ]);

    // approve task
    $response = $this->get(route('api.production.interactives.tasks.approved', $task->uid));

    $response->assertStatus(400);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::WaitingApproval->value,
    ]);

    expect($response->json('message'))->toBe(__('notification.cannotApproveTaskWithoutPic'));
});
