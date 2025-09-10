<?php

use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Hrd\Models\Employee;
use App\Enums\Development\Project\Task\TaskStatus;
use App\Actions\Development\DefineTaskAction;
use Illuminate\Support\Facades\Bus;
use Modules\Development\Jobs\TaskHasBeenCompleteJob;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it('Complete task', function () {
    Bus::fake();

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

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id
    ]);

    $task = DevelopmentProjectTask::factory()
        ->withPic(employee: $worker)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value
        ]);

    $response = $this->getJson(route('api.development.projects.tasks.completed', $task->uid));

    $response->assertStatus(201);

    // current project boards
    $boards = $project->boards->pluck('id')->toArray();
    $currentBoardKey = array_search($task->development_project_board_id, $boards);
    $nextKey = $currentBoardKey + 1;
    $boardId = $task->development_project_board_id;
    if (isset($boards[$nextKey])) {
        $boardId = $boards[$nextKey];
    }

    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::Completed->value,
        'development_project_board_id' => $boardId
    ]);

    $this->assertDatabaseHas('dev_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'is_until_finish' => 1
    ]);

    Bus::assertDispatched(TaskHasBeenCompleteJob::class);
});
