<?php

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Interactive\InteractiveTaskStatus;
use Illuminate\Support\Facades\Bus;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\InteractiveTaskHasBeenCompleteJob;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;

use function Pest\Laravel\assertDatabaseHas;

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
        ->withPic(employee: $worker, withWorkState: true)
        ->withApprovalState()
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    $response = $this->getJson(route('api.production.interactives.tasks.completed', $task->uid));

    $response->assertStatus(201);

    // current project boards
    $boards = $project->boards->pluck('id')->toArray();
    $currentBoardKey = array_search($task->intr_project_board_id, $boards);
    $nextKey = $currentBoardKey + 1;
    $boardId = $task->intr_project_board_id;
    if (isset($boards[$nextKey])) {
        $boardId = $boards[$nextKey];
    }

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::Completed->value,
        'intr_project_board_id' => $boardId,
    ]);

    $this->assertDatabaseHas('intr_project_task_pic_histories', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'is_until_finish' => 1,
    ]);

    assertDatabaseHas('project_task_duration_histories', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'pic_id' => $project->pics->first()->employee_id,
        'is_interactive' => 1,
        'task_type' => 'interactive',
    ]);

    Bus::assertDispatched(InteractiveTaskHasBeenCompleteJob::class);
});

// it('Complete task and check duration summary', function () {
//     Bus::fake();

//     // fake action
//     DefineTaskAction::mock()
//         ->shouldReceive('handle')
//         ->withAnyArgs()
//         ->andReturn([]);

//     $project = InteractiveProject::factory()
//         ->withBoards()
//         ->withPics()
//         ->create();

//     $deadline = now()->addDays(7)->format('Y-m-d H:i');

//     $worker = Employee::factory()
//         ->create([
//             'user_id' => $this->user->id,
//         ]);

//     // update users employee_id
//     \App\Models\User::where('id', $this->user->id)->update([
//         'employee_id' => $worker->id,
//     ]);
// });
