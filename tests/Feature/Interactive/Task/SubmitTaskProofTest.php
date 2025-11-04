<?php

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Interactive\InteractiveTaskStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\SubmitInteractiveTaskJob;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;
use Modules\Production\Models\InteractiveProjectTaskProof;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        permissions: ['submit_interactive_task']
    );

    $this->actingAs($this->user);
});

it('Submit approve task proofs', function () {
    Storage::fake('public');

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

    $boss = Employee::factory()->create();

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
            'boss_id' => $boss->id,
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id,
    ]);

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = InteractiveProjectTask::factory()
        ->withPic(employee: $worker, withWorkState: true, deadline: $deadline)
        ->create([
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => InteractiveTaskStatus::InProgress->value,
        ]);

    $file = UploadedFile::fake()->image('test.jpg');
    $payload = [
        'nas_path' => 'https://google.com',
        'images' => [
            [
                'image' => $file,
            ],
        ],
    ];

    $response = $this->postJson(route('api.production.interactives.tasks.proof.store', $task->uid), $payload);

    $response->assertStatus(201);

    // check task status
    $this->assertDatabaseHas('intr_project_tasks', [
        'id' => $task->id,
        'status' => InteractiveTaskStatus::CheckByPm->value,
        'current_pic_id' => $worker->id,
    ]);

    // get current proof file
    $currentProof = InteractiveProjectTaskProof::with('images')->where('task_id', $task->id)
        ->first();

    // check task proofs
    $this->assertDatabaseHas('intr_task_proofs', [
        'task_id' => $task->id,
        'nas_path' => 'https://google.com',
    ]);

    $this->assertDatabaseCount('intr_task_proof_images', 1);
    $this->assertDatabaseHas('intr_task_proof_images', [
        'intr_task_proof_id' => $currentProof->id,
        'image_path' => $currentProof->images->first()->image_path,
    ]);

    // check actual end time in the deadlines table
    $this->assertDatabaseMissing('intr_project_task_deadlines', [
        'task_id' => $task->id,
        'actual_end_time' => null,
        'employee_id' => $worker->id,
    ]);
    $this->assertDatabaseHas('intr_project_task_deadlines', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    // check first_finish_at in the workstates table
    $this->assertDatabaseHas('intr_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    // task pics should filled with worker boss id
    $this->assertDatabaseMissing('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);
    $this->assertDatabaseHas('intr_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $boss->id,
    ]);
    $this->assertDatabaseHas('intr_project_task_approval_states', [
        'pic_id' => $project->pics->first()->employee_id,
        'task_id' => $task->id,
    ]);

    Bus::assertDispatched(SubmitInteractiveTaskJob::class);
});
