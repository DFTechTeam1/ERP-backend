<?php

use App\Enums\Production\ProjectStatus;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;

/**
 * app:backfill-project-lead-pic is a one-time backfill. For every ongoing/draft project that has at
 * least one PIC but no lead yet, it flags the first PIC (smallest pivot id = earliest created) as
 * is_lead. Projects outside those statuses, projects with no PIC, and projects that already have a
 * lead are left untouched - which also makes a second run a no-op.
 */
function createPic(Project $project, Employee $employee, bool $isLead = false): ProjectPersonInCharge
{
    return ProjectPersonInCharge::create([
        'project_id' => $project->id,
        'pic_id' => $employee->id,
        'is_lead' => $isLead,
    ]);
}

it('flags the first PIC as lead for an ongoing project with multiple PICs', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::OnGoing->value]);
    $first = createPic($project, Employee::factory()->create());
    $second = createPic($project, Employee::factory()->create());

    artisan('app:backfill-project-lead-pic')->assertSuccessful();

    assertDatabaseHas('project_person_in_charges', ['id' => $first->id, 'is_lead' => true]);
    assertDatabaseHas('project_person_in_charges', ['id' => $second->id, 'is_lead' => false]);
    expect(ProjectPersonInCharge::where('project_id', $project->id)->where('is_lead', true)->count())->toBe(1);
});

it('flags the only PIC as lead for a draft project', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Draft->value]);
    $pic = createPic($project, Employee::factory()->create());

    artisan('app:backfill-project-lead-pic')->assertSuccessful();

    assertDatabaseHas('project_person_in_charges', ['id' => $pic->id, 'is_lead' => true]);
});

it('leaves projects outside ongoing/draft untouched', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Completed->value]);
    $pic = createPic($project, Employee::factory()->create());

    artisan('app:backfill-project-lead-pic')->assertSuccessful();

    assertDatabaseHas('project_person_in_charges', ['id' => $pic->id, 'is_lead' => false]);
});

it('does not change a project that already has a lead', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::OnGoing->value]);
    $existingLead = createPic($project, Employee::factory()->create(), isLead: false);
    $manualLead = createPic($project, Employee::factory()->create(), isLead: true);

    artisan('app:backfill-project-lead-pic')->assertSuccessful();

    // the first PIC is NOT promoted - the existing lead is preserved and stays the only lead
    assertDatabaseHas('project_person_in_charges', ['id' => $existingLead->id, 'is_lead' => false]);
    assertDatabaseHas('project_person_in_charges', ['id' => $manualLead->id, 'is_lead' => true]);
    expect(ProjectPersonInCharge::where('project_id', $project->id)->where('is_lead', true)->count())->toBe(1);
});

it('runs cleanly when a qualifying project has no PIC', function () {
    Project::factory()->create(['status' => ProjectStatus::OnGoing->value]);

    artisan('app:backfill-project-lead-pic')->assertSuccessful();

    expect(ProjectPersonInCharge::where('is_lead', true)->count())->toBe(0);
});

it('is idempotent - a second run promotes no one new', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::OnGoing->value]);
    $first = createPic($project, Employee::factory()->create());
    createPic($project, Employee::factory()->create());

    artisan('app:backfill-project-lead-pic')->assertSuccessful();
    artisan('app:backfill-project-lead-pic')->assertSuccessful();

    assertDatabaseHas('project_person_in_charges', ['id' => $first->id, 'is_lead' => true]);
    expect(ProjectPersonInCharge::where('project_id', $project->id)->where('is_lead', true)->count())->toBe(1);
});

it('does not write anything on a dry run', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::OnGoing->value]);
    $pic = createPic($project, Employee::factory()->create());

    artisan('app:backfill-project-lead-pic', ['--dry-run' => true])->assertSuccessful();

    assertDatabaseHas('project_person_in_charges', ['id' => $pic->id, 'is_lead' => false]);
});
