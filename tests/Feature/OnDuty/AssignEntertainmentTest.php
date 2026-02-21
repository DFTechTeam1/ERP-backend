<?php

use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true
    );

    actingAs($this->user);
});

it ("Assign only vj in the main event", function () {
    $project = Project::factory()->create();

    $vj = Employee::factory()->create();

    $response = postJson(route('api.production.incharges.assignVJ', ['projectUid' => $project->uid]), [
        'remove_main_event_uids' => [],
        'remove_after_party_uids' => [],
        'assign_after_party_uids' => [],
        'assign_main_event_uids' => [
            $vj->uid,
        ],
        'main_event_note' => 'Main event VJ',
        'after_party_note' => null,
    ]);

    $response->assertStatus(201);
    
    assertDatabaseHas('project_vjs', [
        'project_id' => $project->id,
        'employee_id' => $vj->id,
    ]);
});

it ('Assign vj for afpat and main event', function () {
    $project = Project::factory()->create();

    $vj = Employee::factory()->create();
    $afpat = Employee::factory()->create();

    $response = postJson(route('api.production.incharges.assignVJ', ['projectUid' => $project->uid]), [
        'remove_main_event_uids' => [],
        'remove_after_party_uids' => [],
        'assign_main_event_uids' => [
            $vj->uid,
        ],
        'main_event_note' => 'Main event VJ',
        'assign_after_party_uids' => [
            $afpat->uid,
        ],
        'after_party_note' => 'After party VJ',
    ]);

    $response->assertStatus(201);
    
    assertDatabaseHas('project_vjs', [
        'project_id' => $project->id,
        'employee_id' => $vj->id,
        'note' => 'Main event VJ',
    ]);

    assertDatabaseHas('project_vj_afpat_attendances', [
        'project_id' => $project->id,
        'employee_id' => $afpat->id,
        'note' => 'After party VJ',
    ]);
});