<?php

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Interactive\InteractiveTaskStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;
use Modules\Production\Models\InteractiveProjectTaskPic;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        permissions: ['revise_interactive_task']
    );

    $this->actingAs($this->user);
});

it('Revise task', function () {
    Storage::fake('public');

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

    $boss = Employee::factory()->create();

    $worker = Employee::factory()
        ->withUser()
        ->create([
            'boss_id' => $boss->id,
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

    // manually update the status
    InteractiveProjectTask::where('id', $task->id)
        ->update([
            'status' => InteractiveTaskStatus::CheckByPm->value,
        ]);

    // manually remove pic
    InteractiveProjectTaskPic::where('task_id', $task->id)
        ->delete();

    // revise
    $response = $this->postJson(route('api.production.interactives.tasks.revised', $task->uid), [
        'reason' => 'revisi',
        'images' => [
            [
                'image' => UploadedFile::fake()->image('test.jpg'),
            ],
        ],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseMissing('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $boss->id,
    ]);

    $this->assertDatabaseHas('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::Revise->value,
    ]);

    $this->assertDatabaseHas('intr_project_task_revises', [
        'task_id' => $task->id,
        'reason' => 'revisi',
    ]);

    // check revisestates
    assertDatabaseHas('interactive_project_task_revisestates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'finish_at' => null,
    ]);

    // check approval state
    assertDatabaseHas('intr_project_task_approval_states', [
        'pic_id' => $project->pics->first()->employee_id,
        'task_id' => $task->id,
        'project_id' => $project->id,
    ]);
});
