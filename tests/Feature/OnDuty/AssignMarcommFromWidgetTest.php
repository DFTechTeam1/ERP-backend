<?php

use Modules\Company\Models\Position;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true
    );

    actingAs($this->user);
});

it('Marcomm PIC assign from widget', function () {
    $project = Project::factory()->create();
    $project1 = Project::factory()->create();

    $marcommPic = Employee::factory()
        ->withUser('password')
        ->create();

    $employeeAsMarcomm = Employee::factory()->create([
        'position_id' => Position::factory()->state([
            'name' => 'Marcomm'
        ])
    ]);

    $response = postJson(route('api.production.incharges.assignMarcommFromWidget', [
        'employeeUid' => $marcommPic->uid,
        'type' => 'mcm'
    ]), [
        'password' => 'password',
        'assignments' => [
            [
                'project_id' => $project->uid,
                'member_ids' => [$employeeAsMarcomm->uid],
                'after_party_member_ids' => []
            ],
            [
                'project_id' => $project1->uid,
                'member_ids' => [$employeeAsMarcomm->uid],
                'after_party_member_ids' => []
            ],
        ],
    ]);
    $response->assertStatus(201);

    assertDatabaseHas('projects', [
        'id' => $project->id,
        'marcomm_attendance_check' => 1,
    ]);

    assertDatabaseHas('project_marcomm_attendances', [
        'project_id' => $project->id,
        'employee_id' => $employeeAsMarcomm->id,
    ]);
});

it ('User skip the attendances for all events', function () {
    $project = Project::factory()->create();
    $project1 = Project::factory()->create();

    $marcommPic = Employee::factory()
        ->withUser('password')
        ->create();

    $response = postJson(route('api.production.incharges.assignMarcommFromWidget', [
        'employeeUid' => $marcommPic->uid,
        'type' => 'mcm'
    ]), [
        'password' => 'password',
        'assignments' => [
            [
                'project_id' => $project->uid,
                'member_ids' => [],
                'after_party_member_ids' => []
            ],
            [
                'project_id' => $project1->uid,
                'member_ids' => [],
                'after_party_member_ids' => []
            ],
        ],
    ]);
    $response->assertStatus(201);

    assertDatabaseHas('projects', [
        'id' => $project->id,
        'marcomm_attendance_check' => 1,
    ]);
    assertDatabaseCount('project_marcomm_attendances', 0);

    assertDatabaseHas('projects', [
        'id' => $project1->id,
        'marcomm_attendance_check' => 1,
    ]);
});


