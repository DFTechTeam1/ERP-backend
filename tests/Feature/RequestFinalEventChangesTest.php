<?php

use Modules\Production\Models\ProjectDeal;
use App\Enums\Production\ProjectDealStatus;
use function Pest\Laravel\postJson;
use Illuminate\Support\Facades\Bus;
use App\Enums\Production\ProjectDealChangeStatus;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it("Request changes return success", function () {
    Bus::fake();

    $projectDeal = ProjectDeal::factory()
        ->create([
            'status' => ProjectDealStatus::Final->value
        ]);

    $payload = [
        'detail_changes' => [
            [
                'old_value' => $projectDeal->name,
                'new_value' => $projectDeal->name . " Update",
                'label' => 'Event Name'
            ]
        ]
    ];

    $projectDealUid = \Illuminate\Support\Facades\Crypt::encryptString($projectDeal->id);

    $response = postJson(route('api.production.project-deal.updateFinal', ['projectDealUid' => $projectDealUid]), $payload);
    logging("RESPONSE REQUEST CHANGES DEAL", $response->json());
    $response->assertStatus(201);

    $this->assertDatabaseHas('project_deal_changes', [
        'project_deal_id' => $projectDeal->id,
        'approval_by' => null,
        'approval_at' => null,
        'status' => ProjectDealChangeStatus::Pending->value
    ]);

    Bus::assertDispatched(\Modules\Production\Jobs\NotifyProjectDealChangesJob::class);
});
