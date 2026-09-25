<?php

use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Services\ProjectService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * setLeadPic() designates one of a project's PMs (PICs) as the Lead. The Lead takes the largest
 * share of the PM reward pot, so exactly one PIC carries is_lead per project - any previous Lead
 * is demoted. Setting a lead for an employee who is not a PIC of the project is rejected.
 */
function leadService(): ProjectService
{
    return app(ProjectService::class);
}

describe('setLeadPic (service)', function () {
    it('flags the chosen PM as lead and demotes the others', function () {
        $project = Project::factory()->create();
        $pmA = Employee::factory()->create();
        $pmB = Employee::factory()->create();
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmA->id, 'is_lead' => true]);
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmB->id, 'is_lead' => false]);

        $response = leadService()->setLeadPic($project->uid, ['employee_uid' => $pmB->uid]);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmB->id, 'is_lead' => true]);
        assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmA->id, 'is_lead' => false]);
    });

    it('moves the lead when called again for a different PM', function () {
        $project = Project::factory()->create();
        $pmA = Employee::factory()->create();
        $pmB = Employee::factory()->create();
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmA->id, 'is_lead' => false]);
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmB->id, 'is_lead' => true]);

        leadService()->setLeadPic($project->uid, ['employee_uid' => $pmA->uid]);

        assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmA->id, 'is_lead' => true]);
        assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmB->id, 'is_lead' => false]);
        // exactly one lead remains
        expect(ProjectPersonInCharge::where('project_id', $project->id)->where('is_lead', true)->count())->toBe(1);
    });

    it('resets the cached project detail so the change is reflected on the next fetch', function () {
        $project = Project::factory()->create();
        $pmA = Employee::factory()->create();
        $pmB = Employee::factory()->create();
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmA->id, 'is_lead' => true]);
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmB->id, 'is_lead' => false]);

        // a stale detail cache exists before the change
        storeCache('detailProject'.$project->id, ['name' => 'STALE']);
        expect(getCache('detailProject'.$project->id))->not->toBeNull();

        leadService()->setLeadPic($project->uid, ['employee_uid' => $pmB->uid]);

        // the cache was cleared, so show() will rebuild with the new main/support labels
        expect(getCache('detailProject'.$project->id))->toBeNull();
    });

    it('rejects setting a lead for an employee who is not a PIC of the project', function () {
        $project = Project::factory()->create();
        $pm = Employee::factory()->create();
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pm->id, 'is_lead' => true]);

        $stranger = Employee::factory()->create();

        $response = leadService()->setLeadPic($project->uid, ['employee_uid' => $stranger->uid]);

        expect($response['error'])->toBeTrue()
            ->and($response['code'])->toBe(400);
        // the real PIC keeps its lead flag; nothing changed
        assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pm->id, 'is_lead' => true]);
    });
});

describe('setLeadPic (e2e)', function () {
    beforeEach(function () {
        actingAs(User::factory()->create());
    });

    it('sets the lead PM via the endpoint', function () {
        $project = Project::factory()->create();
        $pmA = Employee::factory()->create();
        $pmB = Employee::factory()->create();
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmA->id, 'is_lead' => true]);
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmB->id, 'is_lead' => false]);

        $response = $this->postJson("/api/production/project/{$project->uid}/setLeadPic", [
            'employee_uid' => $pmB->uid,
        ]);

        $response->assertStatus(201);
        assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmB->id, 'is_lead' => true]);
        assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmA->id, 'is_lead' => false]);
    });

    it('validates that employee_uid is required', function () {
        $project = Project::factory()->create();

        $this->postJson("/api/production/project/{$project->uid}/setLeadPic", [])
            ->assertStatus(422);
    });
});
