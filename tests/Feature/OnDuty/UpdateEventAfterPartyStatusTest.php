<?php

use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\ProjectStatus;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true
    );

    actingAs($this->user);
});

it('Update after party status return success', function () {
    $projectDeal = ProjectDeal::factory()
        ->create([
            'status' => ProjectDealStatus::Final->value,
        ]);

    $project = Project::factory()
        ->create([
            'name' => fake()->sentence(1),
            'project_deal_id' => $projectDeal->id,
            'status' => ProjectStatus::OnGoing->value,
        ]);

    $response = postJson(route('api.production.project.afpat.status', ['projectUid' => $project->uid]), [
        'with_after_party' => true,
    ]);

    $response->assertStatus(201);

    assertDatabaseHas('projects', [
        'id' => $project->id,
        'with_after_party' => true,
    ]);
});
