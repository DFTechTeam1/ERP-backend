<?php

use App\Actions\Project\DetailCache;
use App\Enums\Production\TaskStatus;
use Illuminate\Support\Facades\Bus;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\TaskIsCompleteJob;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true,
        permissions: [
            'move_board',
            'edit_task_description',
            'add_task_description',
            'delete_task_description',
            'assign_modeller',
            'list_member'
        ]
    );

    $this->actingAs($this->user);

    // create project pic employee
    $this->projectPic = \Modules\Hrd\Models\Employee::find($this->user->employee_id);

    $this->project = Project::factory()
        ->withBoards()
        ->withPics(employee: $this->projectPic)
        ->create();
});

it('Complete task', function () {
    Bus::fake();

    // fake action
    // DefineTaskAction::mock()
    //     ->shouldReceive('handle')
    //     ->withAnyArgs()
    //     ->andReturn([]);
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $task = ProjectTask::factory()
        ->for($this->project, 'project')
        ->for($this->project->boards->first(), 'board')
        ->withPics(employee: $worker, withWorkState: true)
        ->withApprovalState()
        ->create([
            'status' => TaskStatus::OnProgress->value,
            'current_pics' => json_encode([$worker->id]),
            'end_date' => $deadline
        ]);

    $response = $this->getJson(route('api.production.tasks.completed', [
        'projectUid' => $this->project->uid,
        'taskUid' => $task->uid,
    ]));

    $response->assertStatus(201);

    // current project boards
    $boards = $this->project->boards->pluck('id')->toArray();
    $currentBoardKey = array_search($task->intr_project_board_id, $boards);
    $nextKey = $currentBoardKey + 1;
    $boardId = $task->intr_project_board_id;
    if (isset($boards[$nextKey])) {
        $boardId = $boards[$nextKey];
    }

    $this->assertDatabaseHas('project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::Completed->value,
        'project_board_id' => $boardId,
        'end_date' => null
    ]);

    $this->assertDatabaseHas('project_task_duration_histories', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'pic_id' => $this->project->personInCharges->first()->pic_id,
        'is_interactive' => 0,
        'task_type' => 'production',
    ]);

    Bus::assertDispatched(TaskIsCompleteJob::class);
});
