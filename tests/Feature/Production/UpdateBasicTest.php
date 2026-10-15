<?php

use App\Enums\Production\EventType;
use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Modules\Company\Models\ProjectClass;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\ProjectClassChangedJob;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectLead;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * ProjectService::updateBasic() - the "basic information" edit of a project.
 *
 * It resolves the project class (by id, via the `classification` input), converts the `date`
 * input to `project_date`, then inside one transaction:
 *   - updates the project (name, event_type, classification name + id, project_date);
 *   - propagates `project_date` to the linked deal (by id) and the linked lead (by id);
 *   - on a class change, records a ChangeProjectClass activity and queues ProjectClassChangedJob;
 *   - rebuilds the detail cache from formattedBasicData() and returns it.
 * Any throwable rolls everything back and returns an error envelope.
 *
 * The ChangeProjectClass *activity* recording is already covered in ProjectServiceActivityTest;
 * this file focuses on the date propagation (deal + lead), the class-changed job, the deal/lead
 * edge cases, the error path, and the HTTP endpoint.
 *
 * formattedBasicData() runs getProjectTeams() for real (its result feeds the cache), so the actor
 * is a user with an employee_id and a role, and the 'setting' cache is seeded empty.
 */
function ubService(): ProjectService
{
    return app(ProjectService::class);
}

/** Date portion of a model's project_date, regardless of DATE/DATETIME column or casting. */
function ubDate(object $model): string
{
    return Carbon::parse($model->project_date)->toDateString();
}

/**
 * @return array{classA: ProjectClass, deal: ProjectDeal|null, lead: ProjectLead|null, project: Project}
 */
function ubFixture(array $projectOverrides = [], bool $withDeal = true, bool $withLead = true): array
{
    $classA = ProjectClass::factory()->create(['name' => 'Class A']);

    $deal = null;
    $lead = null;
    if ($withDeal) {
        $deal = ProjectDeal::factory()->create(['project_date' => '2020-01-01']);
        if ($withLead) {
            $lead = ProjectLead::factory()->create([
                'project_deal_id' => $deal->id,
                'project_date' => '2020-01-01',
            ]);
        }
    }

    $project = Project::factory()->create(array_merge([
        'name' => 'Original Name',
        'project_class_id' => $classA->id,
        'classification' => $classA->name,
        'project_date' => '2020-01-01',
        'project_deal_id' => $deal?->id,
    ], $projectOverrides));

    return compact('classA', 'deal', 'lead', 'project');
}

/** A valid payload (as BasicUpdate would validate it); `classification` is a project-class id. */
function ubPayload(ProjectClass $class, array $overrides = []): array
{
    return array_merge([
        'name' => 'Updated Name',
        'date' => '2026-10-01',
        'event_type' => EventType::cases()[0]->value,
        'classification' => (string) $class->id,
    ], $overrides);
}

beforeEach(function () {
    Queue::fake();
    Cache::forever('setting', []);

    // getProjectTeams (via formattedBasicData) reads $user->roles[0]->id and $user->employee_id.
    $employee = Employee::factory()->withUser()->create();
    $this->actor = User::where('employee_id', $employee->id)->firstOrFail();
    $this->actor->assignRole(Role::firstOrCreate(['name' => BaseRole::Production->value, 'guard_name' => 'sanctum']));
    actingAs($this->actor);
});

it('updates the project and propagates project_date to the linked deal and lead', function () {
    ['classA' => $classA, 'deal' => $deal, 'lead' => $lead, 'project' => $project] = ubFixture();

    $response = ubService()->updateBasic(ubPayload($classA), $project->uid);

    expect($response['error'])->toBeFalse()
        ->and($response['message'])->toBe(__('global.successUpdateBasicInformation'));

    assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Updated Name',
        'project_class_id' => $classA->id,
        'classification' => $classA->name,
    ]);

    // project_date set on the project, and mirrored onto the deal AND the lead
    expect(ubDate($project->fresh()))->toBe('2026-10-01')
        ->and(ubDate($deal->fresh()))->toBe('2026-10-01')
        ->and(ubDate($lead->fresh()))->toBe('2026-10-01');
});

it('updates the project without error when there is no linked deal', function () {
    ['classA' => $classA, 'project' => $project] = ubFixture(withDeal: false);

    $response = ubService()->updateBasic(ubPayload($classA), $project->uid);

    expect($response['error'])->toBeFalse()
        ->and(ubDate($project->fresh()))->toBe('2026-10-01');
    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Updated Name']);
});

it('updates the deal date but skips the lead when the deal has no lead', function () {
    ['classA' => $classA, 'deal' => $deal, 'project' => $project] = ubFixture(withLead: false);

    $response = ubService()->updateBasic(ubPayload($classA), $project->uid);

    expect($response['error'])->toBeFalse()
        ->and(ubDate($deal->fresh()))->toBe('2026-10-01');
});

it('dispatches ProjectClassChangedJob when the project class changes', function () {
    ['project' => $project] = ubFixture();
    $classB = ProjectClass::factory()->create(['name' => 'Class B']);

    $response = ubService()->updateBasic(ubPayload($classB), $project->uid);

    expect($response['error'])->toBeFalse();
    assertDatabaseHas('projects', ['id' => $project->id, 'project_class_id' => $classB->id]);
    Queue::assertPushed(ProjectClassChangedJob::class);
});

it('does not dispatch ProjectClassChangedJob when the class is unchanged', function () {
    ['classA' => $classA, 'project' => $project] = ubFixture();

    $response = ubService()->updateBasic(ubPayload($classA), $project->uid);

    expect($response['error'])->toBeFalse();
    Queue::assertNotPushed(ProjectClassChangedJob::class);
});

it('rolls back and returns an error when the classification id is unknown', function () {
    ['classA' => $classA, 'project' => $project] = ubFixture();

    // Unknown class id -> projectClassRepo->show() is null -> reading ->id throws before any write.
    $response = ubService()->updateBasic(
        ubPayload($classA, ['classification' => '999999', 'name' => 'Should Not Persist']),
        $project->uid
    );

    expect($response['error'])->toBeTrue();
    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original Name']);
});

/**
 * -----------------------------------------------------------------------------
 * End-to-end: PUT /api/production/project/basic/{projectId} (auth.session guarded)
 * -----------------------------------------------------------------------------
 */
it('[e2e] updates basic info through the HTTP endpoint', function () {
    ['classA' => $classA, 'lead' => $lead, 'project' => $project] = ubFixture();

    $response = $this->putJson("/api/production/project/basic/{$project->uid}", ubPayload($classA));

    $response->assertSuccessful();
    expect($response->json('message'))->toBe(__('global.successUpdateBasicInformation'));

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Updated Name']);
    expect(ubDate($lead->fresh()))->toBe('2026-10-01');
});

it('[e2e] rejects a payload missing a required field', function () {
    ['classA' => $classA, 'project' => $project] = ubFixture();

    $payload = ubPayload($classA);
    unset($payload['name']); // `name` is required

    $this->putJson("/api/production/project/basic/{$project->uid}", $payload)
        ->assertStatus(422);
});

it('[e2e] rejects an unauthenticated request', function () {
    ['classA' => $classA, 'project' => $project] = ubFixture();

    auth()->forgetGuards();

    $this->putJson("/api/production/project/basic/{$project->uid}", ubPayload($classA))
        ->assertUnauthorized();
});
