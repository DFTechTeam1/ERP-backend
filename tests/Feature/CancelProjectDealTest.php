<?php

use App\Enums\Production\ProjectDealStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Production\Jobs\ProjectDealCanceledJob;
use Modules\Production\Models\ProjectDeal;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Cancel project deal when status is final', function () {
    $project = ProjectDeal::factory()
        ->create([
            'status' => ProjectDealStatus::Final->value,
        ]);

    $response = postJson(route('api.production.project-deal.cancel', ['projectDealUid' => Crypt::encryptString($project->id)]), [
        'reason' => 'Batal',
    ]);
    $response->assertStatus(400);

    expect($response->json())->toHaveKeys(['message', 'data']);

    expect($response->json()['message'])->toEqual(__('notification.eventCannotBeCancel'));

    $this->assertDatabaseHas('project_deals', [
        'id' => $project->id,
        'status' => ProjectDealStatus::Final->value,
        'cancel_at' => null,
        'cancel_by' => null,
    ]);
});

it('Cancel project deal return success', function () {
    Bus::fake();

    $project = ProjectDeal::factory()
        ->create([
            'status' => ProjectDealStatus::Temporary->value,
        ]);

    $response = postJson(route('api.production.project-deal.cancel', ['projectDealUid' => Crypt::encryptString($project->id)]), [
        'reason' => 'Batal',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_deals', [
        'id' => $project->id,
        'status' => ProjectDealStatus::Canceled->value,
        'cancel_reason' => 'Batal',
    ]);

    Bus::assertDispatched(ProjectDealCanceledJob::class);
});
