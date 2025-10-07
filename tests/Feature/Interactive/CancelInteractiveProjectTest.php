<?php

use App\Enums\Production\ProjectStatus;
use Illuminate\Support\Facades\Bus;
use Modules\Production\Jobs\InteractiveProjectHasBeenCanceledJob;
use Modules\Production\Models\InteractiveProject;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it('Cancel project has been success', function () {
    Bus::fake();

    $interactiveProject = InteractiveProject::factory()
        ->withBoards()
        ->create([
            'status' => ProjectStatus::OnGoing->value,
        ]);
    $interactiveUid = $interactiveProject->uid;

    $response = $this->getJson(route('api.production.interactives.cancel', ['interactiveUid' => $interactiveUid]));

    $response->assertStatus(201);
    $response->assertJson([
        'message' => __('notification.projectHasBeenCanceled'),
    ]);

    $this->assertDatabaseHas('interactive_projects', [
        'uid' => $interactiveUid,
        'status' => ProjectStatus::Canceled->value,
    ]);

    Bus::assertDispatched(InteractiveProjectHasBeenCanceledJob::class);
});
