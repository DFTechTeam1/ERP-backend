<?php

use App\Actions\Project\DetailCache;
use App\Enums\Production\EventType;
use App\Enums\Production\ProjectStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use App\Services\Geocoding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Modules\Company\Models\City;
use Modules\Company\Models\State;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\NotifyProjectStatusChangedJob;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectLead;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * ProjectService::updateMoreDetail() - the "more detail" edit of a project.
 *
 * The method fans a single payload out to three tables inside one transaction:
 *   - projects      (by uid)  - every field except `pic`, plus a geocoded longitude/latitude,
 *                               the resolved city_name, and a json-encoded led_detail.
 *   - project_deals (by id)   - venue, country_id, state_id, city_id, led_detail, led_area, collaboration.
 *   - project_leads (by id)   - venue, city_id, collaboration.
 * When the status actually changes it queues NotifyProjectStatusChangedJob (the WhatsApp notice).
 * Any throwable rolls the transaction back and returns an error envelope.
 *
 * Collaborators that are orthogonal to this method are controlled:
 *   - Geocoding::getCoordinate() is mocked (no outbound HTTP) - it drives the lat/long branch.
 *   - DetailCache::handle() (the heavy full-detail rebuild) is stubbed and its return value is
 *     what the success envelope carries back as `data`.
 *   - NotifyProjectStatusChangedJob is faked via Queue::fake().
 * getProjectTeams() runs for real (its result is unused by this method), so the actor is a user
 * with an employee_id and a role, and the 'setting' cache is seeded empty for deterministic reads.
 */
const UMD_DETAIL_STUB = ['detail' => 'STUB_FROM_DETAIL_CACHE'];

function umdService(): ProjectService
{
    return app(ProjectService::class);
}

/** Normalise a led_detail attribute to an array regardless of model casting / JSON-column spacing. */
function umdLed(mixed $value): mixed
{
    return is_string($value) ? json_decode($value, true) : $value;
}

/** Resolve the project's stored status exactly as the service reads it (so a strict !== holds). */
function umdLoadedStatus(string $uid): mixed
{
    return app(ProjectRepository::class)
        ->show(uid: $uid, select: 'id,status,project_deal_id')
        ->status;
}

/**
 * Bind the Geocoding mock. Pass null to simulate "no match" (empty array).
 *
 * @param  array{longitude: float, latitude: float}|null  $coordinate
 */
function umdMockGeocoding(?array $coordinate): void
{
    test()->mock(Geocoding::class, function ($mock) use ($coordinate) {
        $mock->shouldReceive('getCoordinate')->andReturn($coordinate ?? []);
    });
}

/**
 * Build a project linked to a deal + lead, plus a City and State to resolve against.
 *
 * @return array{project: Project, deal: ProjectDeal, lead: ProjectLead, city: City, state: State}
 */
function umdFixture(array $projectOverrides = []): array
{
    // City factory builds a coherent country + state graph; derive the state from the city so
    // every *_id the payload carries references a row that actually exists (projects.country_id
    // has a real FK to countries.id).
    $city = City::factory()->create(['name' => 'Surabaya']);
    $state = State::find($city->state_id);

    $deal = ProjectDeal::factory()->create([
        'venue' => 'Old Venue',
        'collaboration' => 'old-collab',
    ]);
    $lead = ProjectLead::factory()->create([
        'project_deal_id' => $deal->id,
        'venue' => 'Old Venue',
        'collaboration' => 'old-collab',
        'city_id' => null,
    ]);
    $project = Project::factory()->create(array_merge([
        'project_deal_id' => $deal->id,
        'status' => ProjectStatus::Draft->value,
        'venue' => 'Hotel Brawijaya',
        'longitude' => '106.8277658',
        'latitude' => '-6.1875613',
    ], $projectOverrides));

    return compact('project', 'deal', 'lead', 'city', 'state');
}

/**
 * A full, valid payload (as MoreDetailUpdate would validate it).
 */
function umdPayload(City $city, State $state, array $overrides = []): array
{
    return array_merge([
        'client_portal' => 'portal-updated',
        'collaboration' => 'new-collab',
        'event_type' => EventType::cases()[0]->value,
        'note' => 'updated note',
        'status' => ProjectStatus::Completed->value,
        'venue' => 'Grand City Convex',
        'country_id' => $city->country_id,
        'state_id' => $state->id,
        'city_id' => $city->id,
        'led_area' => 12,
        'led_detail' => [['name' => 'main', 'total' => 2]],
        'pic' => [],
    ], $overrides);
}

beforeEach(function () {
    Queue::fake();

    // Deterministic, empty settings so getSettingByKey() returns null on every key
    // (getProjectTeams reads a few of them).
    Cache::forever('setting', []);

    // Heavy full-detail rebuild is stubbed; its return value is the success envelope's data.
    $this->mock(DetailCache::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn(UMD_DETAIL_STUB);
    });

    // Default geocoding result; individual tests override as needed.
    umdMockGeocoding(['longitude' => 112.75, 'latitude' => -7.25]);

    // getProjectTeams reads $user->roles[0]->id and $user->employee_id, so the actor needs both.
    $employee = Employee::factory()->withUser()->create();
    $this->actor = User::where('employee_id', $employee->id)->firstOrFail();
    $this->actor->assignRole(Role::firstOrCreate(['name' => BaseRole::Production->value, 'guard_name' => 'sanctum']));
    actingAs($this->actor);
});

it('updates the project, its deal and its lead, and returns the detail-cache payload', function () {
    ['project' => $project, 'deal' => $deal, 'lead' => $lead, 'city' => $city, 'state' => $state] = umdFixture();

    $payload = umdPayload($city, $state, ['status' => umdLoadedStatus($project->uid)]);

    $response = umdService()->updateMoreDetail($payload, $project->uid);

    expect($response['error'])->toBeFalse()
        ->and($response['message'])->toBe(__('global.successUpdateBasicInformation'))
        ->and($response['data'])->toBe(UMD_DETAIL_STUB);

    // project: fanned-out fields + resolved city_name
    assertDatabaseHas('projects', [
        'id' => $project->id,
        'venue' => 'Grand City Convex',
        'collaboration' => 'new-collab',
        'client_portal' => 'portal-updated',
        'note' => 'updated note',
        'city_id' => $city->id,
        'state_id' => $state->id,
        'city_name' => 'Surabaya',
        'led_area' => 12,
    ]);

    // project_deal: the subset the method mirrors onto the deal
    assertDatabaseHas('project_deals', [
        'id' => $deal->id,
        'venue' => 'Grand City Convex',
        'city_id' => $city->id,
        'state_id' => $state->id,
        'collaboration' => 'new-collab',
        'led_area' => 12,
    ]);

    // project_lead: venue/city_id/collaboration mirrored (regression: was matched by the wrong key)
    assertDatabaseHas('project_leads', [
        'id' => $lead->id,
        'venue' => 'Grand City Convex',
        'city_id' => $city->id,
        'collaboration' => 'new-collab',
    ]);
});

it('never writes the `pic` key to the project row', function () {
    ['project' => $project, 'city' => $city, 'state' => $state] = umdFixture();

    // `pic` is an array; if it were not excluded the mass update would hit an unknown `pic` column
    // and fail. A clean success proves it is stripped before the project update.
    $payload = umdPayload($city, $state, [
        'status' => umdLoadedStatus($project->uid),
        'pic' => ['some-uid-a', 'some-uid-b'],
    ]);

    $response = umdService()->updateMoreDetail($payload, $project->uid);

    expect($response['error'])->toBeFalse();
    assertDatabaseHas('projects', ['id' => $project->id, 'venue' => 'Grand City Convex']);
});

it('stores the geocoded coordinates on the project when geocoding returns a match', function () {
    ['project' => $project, 'city' => $city, 'state' => $state] = umdFixture();
    umdMockGeocoding(['longitude' => 112.75, 'latitude' => -7.25]);

    umdService()->updateMoreDetail(
        umdPayload($city, $state, ['status' => umdLoadedStatus($project->uid)]),
        $project->uid
    );

    $fresh = $project->fresh();
    expect((float) $fresh->longitude)->toBe(112.75)
        ->and((float) $fresh->latitude)->toBe(-7.25);
});

it('leaves the existing coordinates untouched when geocoding returns no match', function () {
    ['project' => $project, 'city' => $city, 'state' => $state] = umdFixture();
    $before = $project->fresh();

    umdMockGeocoding(null); // empty -> lat/long branch skipped

    $response = umdService()->updateMoreDetail(
        umdPayload($city, $state, ['status' => umdLoadedStatus($project->uid)]),
        $project->uid
    );

    expect($response['error'])->toBeFalse();

    $after = $project->fresh();
    // the update still ran (venue changed) but coordinates were not overwritten
    expect($after->venue)->toBe('Grand City Convex')
        ->and($after->longitude)->toBe($before->longitude)
        ->and($after->latitude)->toBe($before->latitude);
});

it('queues the status-changed notification when the status changes', function () {
    ['project' => $project, 'city' => $city, 'state' => $state] = umdFixture(['status' => ProjectStatus::Draft->value]);

    umdService()->updateMoreDetail(
        umdPayload($city, $state, ['status' => ProjectStatus::Completed->value]),
        $project->uid
    );

    Queue::assertPushed(NotifyProjectStatusChangedJob::class);
});

it('does not queue the notification when the status is unchanged', function () {
    ['project' => $project, 'city' => $city, 'state' => $state] = umdFixture(['status' => ProjectStatus::Draft->value]);

    // pass back the exact persisted status so the strict comparison sees no change
    $response = umdService()->updateMoreDetail(
        umdPayload($city, $state, ['status' => umdLoadedStatus($project->uid)]),
        $project->uid
    );

    expect($response['error'])->toBeFalse();
    Queue::assertNotPushed(NotifyProjectStatusChangedJob::class);
});

it('json-encodes a provided led_detail onto both the project and the deal', function () {
    ['project' => $project, 'deal' => $deal, 'city' => $city, 'state' => $state] = umdFixture();

    umdService()->updateMoreDetail(
        umdPayload($city, $state, [
            'status' => umdLoadedStatus($project->uid),
            'led_detail' => [['name' => 'main', 'total' => 2]],
        ]),
        $project->uid
    );

    $expected = [['name' => 'main', 'total' => 2]];
    expect(umdLed($project->fresh()->led_detail))->toBe($expected)
        ->and(umdLed($deal->fresh()->led_detail))->toBe($expected);
});

it('stores an empty json array when led_detail is empty or missing', function () {
    ['project' => $project, 'deal' => $deal, 'city' => $city, 'state' => $state] = umdFixture();

    $payload = umdPayload($city, $state, ['status' => umdLoadedStatus($project->uid)]);
    unset($payload['led_detail']);

    umdService()->updateMoreDetail($payload, $project->uid);

    expect(umdLed($project->fresh()->led_detail))->toBe([])
        ->and(umdLed($deal->fresh()->led_detail))->toBe([]);
});

it('rolls back and returns an error envelope when the status value is invalid', function () {
    ['project' => $project, 'city' => $city, 'state' => $state] = umdFixture(['venue' => 'Hotel Brawijaya']);

    // 999 is not a ProjectStatus case -> ProjectStatus::from() throws before any write.
    $response = umdService()->updateMoreDetail(
        umdPayload($city, $state, ['status' => 999, 'venue' => 'Should Not Persist']),
        $project->uid
    );

    expect($response['error'])->toBeTrue();
    assertDatabaseHas('projects', ['id' => $project->id, 'venue' => 'Hotel Brawijaya']);
    Queue::assertNotPushed(NotifyProjectStatusChangedJob::class);
});

it('rolls back and returns an error envelope when the city cannot be resolved', function () {
    ['project' => $project, 'city' => $city, 'state' => $state] = umdFixture(['venue' => 'Hotel Brawijaya']);

    // Non-existent city_id -> City::find() is null -> reading ->name throws; status kept unchanged
    // so the (pre-throw) status-changed dispatch is not reached.
    $response = umdService()->updateMoreDetail(
        umdPayload($city, $state, [
            'status' => umdLoadedStatus($project->uid),
            'city_id' => 999999,
            'venue' => 'Should Not Persist',
        ]),
        $project->uid
    );

    expect($response['error'])->toBeTrue();
    assertDatabaseHas('projects', ['id' => $project->id, 'venue' => 'Hotel Brawijaya']);
    Queue::assertNotPushed(NotifyProjectStatusChangedJob::class);
});

/**
 * -----------------------------------------------------------------------------
 * End-to-end: PUT /api/production/project/moreDetail/{uid} (auth.session guarded)
 * -----------------------------------------------------------------------------
 */
it('[e2e] updates the project through the HTTP endpoint', function () {
    ['project' => $project, 'deal' => $deal, 'lead' => $lead, 'city' => $city, 'state' => $state] = umdFixture();

    $payload = umdPayload($city, $state, ['status' => umdLoadedStatus($project->uid)]);

    $response = $this->putJson("/api/production/project/moreDetail/{$project->uid}", $payload);

    $response->assertSuccessful();
    expect($response->json('message'))->toBe(__('global.successUpdateBasicInformation'))
        ->and($response->json('data'))->toBe(UMD_DETAIL_STUB);

    assertDatabaseHas('projects', ['id' => $project->id, 'venue' => 'Grand City Convex', 'city_name' => 'Surabaya']);
    assertDatabaseHas('project_deals', ['id' => $deal->id, 'venue' => 'Grand City Convex']);
    assertDatabaseHas('project_leads', ['id' => $lead->id, 'venue' => 'Grand City Convex']);
});

it('[e2e] rejects a payload missing a required field', function () {
    ['project' => $project, 'city' => $city, 'state' => $state] = umdFixture();

    $payload = umdPayload($city, $state);
    unset($payload['venue']); // `venue` is required

    $this->putJson("/api/production/project/moreDetail/{$project->uid}", $payload)
        ->assertStatus(422);
});

it('[e2e] rejects an unauthenticated request', function () {
    ['project' => $project, 'city' => $city, 'state' => $state] = umdFixture();

    // drop the beforeEach authentication for this case
    auth()->forgetGuards();

    $this->putJson("/api/production/project/moreDetail/{$project->uid}", umdPayload($city, $state))
        ->assertUnauthorized();
});
