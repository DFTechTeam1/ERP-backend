<?php

use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Development\Models\DevelopmentProjectTaskAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Actions\Development\DefineTaskAction;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Delete image return success', function () {
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

    // create tasks
    $task = DevelopmentProjectTask::factory()
        ->for($project)
        ->create([
            'development_project_board_id' => $project->boards->first()->id
        ]);

    // create attachments
    $attachment = DevelopmentProjectTaskAttachment::create([
        'task_id' => $task->id,
        'file_path' => 'test.jpg'
    ]);

    $file = UploadedFile::fake()->image('test.jpg');
    // put file manually
    Storage::putFileAs('public/development/projects/tasks', $file, 'test.jpg');

    // check if file exists before deleting
    Storage::disk('public')->exists('development/projects/tasks/test.jpg');

    $response = $this->deleteJson(route('api.development.projects.tasks.attachments.destroy', [
        'projectUid' => $project->uid,
        'taskUid' => $task->uid,
        'attachmentId' => $attachment->uid
    ]));

    $response->assertStatus(201);

    // check if file already deleted from storage
    Storage::disk('public')->missing('development/projects/tasks/test.jpg');

    // check database attachments is missing
    $this->assertDatabaseMissing('development_project_task_attachments', [
        'id' => $attachment->id
    ]);
});