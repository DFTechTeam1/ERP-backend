<?php

use Illuminate\Support\Facades\Bus;
use Modules\Production\Jobs\AssignInteractiveProjectPicJob;
use Modules\Production\Models\InteractiveProject;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        permissions: ['assign_interactive_pic']
    );

    $this->actingAs($this->user);
});

it('Assign PIC to existing project', function () {
    Bus::fake();

    $project = InteractiveProject::factory()
        ->withBoards()
        ->create();

    $employee = \Modules\Hrd\Models\Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.production.interactives.assignPic', $project->uid), [
        'pics' => [
            [
                'employee_uid' => $employee->uid,
            ],
        ],
    ]);

    $response->assertStatus(201);

    expect($response->json()['message'])->toBe(__('notification.successAssignPicToProject'));

    $this->assertDatabaseHas('interactive_project_pics', [
        'intr_project_id' => $project->id,
        'employee_id' => $employee->id,
    ]);

    Bus::assertDispatched(AssignInteractiveProjectPicJob::class);
});

it('Subtitute PIC on existing project', function () {
    Bus::fake();

    $project = InteractiveProject::factory()
        ->withBoards()
        ->withPics(2)
        ->create();

    $oldPic = $project->pics->first();

    $newEmployee = \Modules\Hrd\Models\Employee::factory()
        ->withUser()
        ->create();

    $response = $this->postJson(route('api.production.interactives.substitutePic', $project->uid), [
        'pics' => [
            [
                'employee_uid' => $newEmployee->uid,
            ],
        ],
        'remove' => [
            [
                'employee_uid' => $oldPic->employee->uid,
            ],
        ],
    ]);

    $response->assertStatus(201);

    expect($response->json()['message'])->toBe(__('notification.successSubtitutePicToProject'));

    // check old pic removed
    $this->assertDatabaseMissing('interactive_project_pics', [
        'intr_project_id' => $project->id,
        'employee_id' => $oldPic->employee_id,
    ]);

    // check new pic added
    $this->assertDatabaseHas('interactive_project_pics', [
        'intr_project_id' => $project->id,
        'employee_id' => $newEmployee->id,
    ]);

    Bus::assertDispatched(AssignInteractiveProjectPicJob::class);
});
