<?php

use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Development\Models\DevelopmentTaskProof;
use Modules\Development\Models\DevelopmentProjectTaskPicWorkstate;
use Modules\Hrd\Models\Employee;
use App\Enums\Development\Project\Task\TaskStatus;
use App\Actions\Development\DefineTaskAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it('Submit approve task proofs', function () {
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

    $boss = Employee::factory()->create();

    $worker = Employee::factory()
        ->create([
            'user_id' => $this->user->id,
            'boss_id' => $boss->id
        ]);

    // update users employee_id
    \App\Models\User::where('id', $this->user->id)->update([
        'employee_id' => $worker->id
    ]);

    $deadline = now()->addDays(7)->format('Y-m-d H:i');

    $task = DevelopmentProjectTask::factory()
        ->withPic(employee: $worker, withWorkState: true, deadline: $deadline)
        ->create([
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'deadline' => $deadline,
            'status' => TaskStatus::InProgress->value
        ]);

    $file = UploadedFile::fake()->image('test.jpg');
    $payload = [
        'nas_path' => 'https://google.com',
        'images' => [
            [
                'image' => $file
            ]
        ]
    ];

    $response = $this->postJson(route('api.development.projects.tasks.proof.store', $task->uid), $payload);
    logging('submit task', $response->json());
    $response->assertStatus(201);

    // check task status
    $this->assertDatabaseHas('development_project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::CheckByPm->value,
        'current_pic_id' => $worker->id
    ]);

    // get current proof file
    $currentProof = DevelopmentTaskProof::with('images')->where('task_id', $task->id)
        ->first();

    // check task proofs
    $this->assertDatabaseHas('development_task_proofs', [
        'task_id' => $task->id,
        'nas_path' => 'https://google.com',
    ]);

    $this->assertDatabaseCount('development_task_proof_images', 1);
    $this->assertDatabaseHas('development_task_proof_images', [
        'development_task_proof_id' => $currentProof->id,
        'image_path' => $currentProof->images->first()->image_path,
    ]);

    // check actual end time in the deadlines table
    $this->assertDatabaseMissing('development_project_task_deadlines', [
        'task_id' => $task->id,
        'actual_end_time' => null,
        'employee_id' => $worker->id
    ]);
    $this->assertDatabaseHas('development_project_task_deadlines', [
        'task_id' => $task->id,
        'employee_id' => $worker->id
    ]);

    // check finished_at in the workstates table
    $this->assertDatabaseMissing('dev_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'finished_at' => null
    ]);
    $this->assertDatabaseHas('dev_project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    // task pics should filled with worker boss id
    $this->assertDatabaseMissing('development_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $worker->id
    ]);
    $this->assertDatabaseHas('development_project_task_pics', [
        'task_id' => $task->id,
        'employee_id' => $boss->id
    ]);
});
