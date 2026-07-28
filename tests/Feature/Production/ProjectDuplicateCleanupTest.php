<?php

use App\Console\Commands\ProjectDuplicateCleanup;
use App\Enums\Production\ProjectStatus;
use Illuminate\Support\Collection;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectBoard;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Services\ProjectService;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * The command's internals are protected, so bind a closure to an instance to
 * exercise detection and classification directly.
 */
function invokeOnCommand(string $method, mixed ...$arguments): mixed
{
    $command = new ProjectDuplicateCleanup;

    return Closure::bind(
        fn () => $this->{$method}(...$arguments),
        $command,
        ProjectDuplicateCleanup::class
    )();
}

/**
 * Detection scans the whole projects table, which may already hold rows this
 * test did not create, so results are narrowed to the names under test.
 *
 * @param  array<int, string>  $names
 * @return Collection<int, Illuminate\Database\Eloquent\Collection<int, Project>>
 */
function duplicateGroupsFor(array $names): Collection
{
    return invokeOnCommand('getDuplicateProjectGroups')
        ->values()
        ->filter(fn ($group) => in_array($group->first()->name, $names, true))
        ->values();
}

/**
 * @return array<int, int> project ids of one group, sorted
 */
function duplicateIdsFor(string $name): array
{
    return duplicateGroupsFor([$name])
        ->flatMap(fn ($group) => $group->pluck('id'))
        ->sort()->values()->all();
}

function makeProject(string $name, string $date, ProjectStatus $status, ?int $dealId = null): Project
{
    return Project::factory()->create([
        'name' => $name,
        'project_date' => $date,
        'status' => $status->value,
        'project_deal_id' => $dealId,
    ]);
}

describe('detection', function () {
    it('flags projects sharing a name on the same project date', function () {
        $first = makeProject('Wedding Rina & Adi', '2026-08-01', ProjectStatus::OnGoing);
        $second = makeProject('Wedding Rina & Adi', '2026-08-01', ProjectStatus::Draft);

        expect(duplicateIdsFor('Wedding Rina & Adi'))->toBe([$first->id, $second->id]);
    });

    it('does not flag the same name on different project dates', function () {
        makeProject('Gala Dinner Astra', '2026-08-01', ProjectStatus::OnGoing);
        makeProject('Gala Dinner Astra', '2026-09-15', ProjectStatus::Draft);

        expect(duplicateIdsFor('Gala Dinner Astra'))->toBe([]);
    });

    it('does not flag different names on the same project date', function () {
        makeProject('Gala Dinner Astra', '2026-08-01', ProjectStatus::OnGoing);
        makeProject('Gala Dinner Toyota', '2026-08-01', ProjectStatus::Draft);

        expect(duplicateIdsFor('Gala Dinner Astra'))->toBe([]);
    });

    it('ignores status when grouping duplicates', function () {
        $ids = collect([ProjectStatus::Canceled, ProjectStatus::Completed, ProjectStatus::Draft, ProjectStatus::PartialComplete])
            ->map(fn (ProjectStatus $status) => makeProject('Launching Produk X', '2026-08-01', $status)->id)
            ->sort()->values()->all();

        expect(duplicateIdsFor('Launching Produk X'))->toBe($ids);
    });

    it('keeps duplicate groups separated per name and date pair', function () {
        makeProject('Event A', '2026-08-01', ProjectStatus::OnGoing);
        makeProject('Event A', '2026-08-01', ProjectStatus::Draft);
        makeProject('Event B', '2026-08-02', ProjectStatus::Completed);
        makeProject('Event B', '2026-08-02', ProjectStatus::Revise);

        // Shares a name with group A and a date with group B, but neither pair.
        makeProject('Event A', '2026-08-02', ProjectStatus::Draft);

        expect(duplicateGroupsFor(['Event A', 'Event B'])->count())->toBe(2);
    });
});

describe('classification', function () {
    it('never deletes rows holding separate deals', function () {
        $deals = ProjectDeal::factory()->count(2)->create();
        makeProject('Wedding Sinta', '2026-08-01', ProjectStatus::Draft, $deals[0]->id);
        makeProject('Wedding Sinta', '2026-08-01', ProjectStatus::OnGoing, $deals[1]->id);

        $result = invokeOnCommand('classifyDuplicateGroup', duplicateGroupsFor(['Wedding Sinta'])->first());

        expect($result['verdict'])->toBe('review')
            ->and($result['keep'])->toBeNull()
            ->and($result['delete'])->toBeEmpty();
    });

    it('keeps the most advanced status and deletes the rest', function () {
        $deal = ProjectDeal::factory()->create();
        $draft = makeProject('Wedding Bella', '2026-08-01', ProjectStatus::Draft, $deal->id);
        $ongoing = makeProject('Wedding Bella', '2026-08-01', ProjectStatus::OnGoing, $deal->id);

        $result = invokeOnCommand('classifyDuplicateGroup', duplicateGroupsFor(['Wedding Bella'])->first());

        expect($result['verdict'])->toBe('duplicate')
            ->and($result['keep']->id)->toBe($ongoing->id)
            ->and($result['delete']->pluck('id')->all())->toBe([$draft->id]);
    });

    it('keeps one copy when the whole group shares a status and a deal', function () {
        $deal = ProjectDeal::factory()->create();
        $ids = collect(range(1, 7))
            ->map(fn () => makeProject('Sound Check Nusantara', '2026-08-01', ProjectStatus::Draft, $deal->id)->id);

        $result = invokeOnCommand('classifyDuplicateGroup', duplicateGroupsFor(['Sound Check Nusantara'])->first());

        expect($result['keep']->id)->toBe($ids->min())
            ->and($result['delete']->pluck('id')->sort()->values()->all())
            ->toBe($ids->reject(fn (int $id) => $id === $ids->min())->sort()->values()->all());
    });

    it('breaks a status tie on the copy carrying the most downstream records', function () {
        $bare = makeProject('Corporate Gathering BCA', '2026-08-01', ProjectStatus::Completed);
        $busy = makeProject('Corporate Gathering BCA', '2026-08-01', ProjectStatus::Completed);

        ProjectBoard::factory()->count(3)->create(['project_id' => $busy->id]);

        $result = invokeOnCommand('classifyDuplicateGroup', duplicateGroupsFor(['Corporate Gathering BCA'])->first());

        expect($result['keep']->id)->toBe($busy->id)
            ->and($result['delete']->pluck('id')->all())->toBe([$bare->id]);
    });

    it('ranks a cancelled copy below a draft', function () {
        $canceled = makeProject('Product Launch Wuling', '2026-08-01', ProjectStatus::Canceled);
        $draft = makeProject('Product Launch Wuling', '2026-08-01', ProjectStatus::Draft);

        $result = invokeOnCommand('classifyDuplicateGroup', duplicateGroupsFor(['Product Launch Wuling'])->first());

        expect($result['keep']->id)->toBe($draft->id)
            ->and($result['delete']->pluck('id')->all())->toBe([$canceled->id]);
    });
});

describe('dry run', function () {
    it('deletes nothing without --force', function () {
        $deal = ProjectDeal::factory()->create();
        $draft = makeProject('Wedding Clara', '2026-08-01', ProjectStatus::Draft, $deal->id);
        $ongoing = makeProject('Wedding Clara', '2026-08-01', ProjectStatus::OnGoing, $deal->id);

        artisan('app:project-duplicate-cleanup')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        assertDatabaseHas('projects', ['id' => $draft->id]);
        assertDatabaseHas('projects', ['id' => $ongoing->id]);
    });
});

describe('cleanup', function () {
    it('deletes the redundant copies and keeps the survivor', function () {
        $deal = ProjectDeal::factory()->create();
        $draft = makeProject('Wedding Dinda', '2026-08-01', ProjectStatus::Draft, $deal->id);
        $ongoing = makeProject('Wedding Dinda', '2026-08-01', ProjectStatus::OnGoing, $deal->id);

        artisan('app:project-duplicate-cleanup --force')
            ->expectsConfirmation('Delete these projects permanently?', 'yes')
            ->assertSuccessful();

        assertDatabaseMissing('projects', ['id' => $draft->id]);
        assertDatabaseHas('projects', ['id' => $ongoing->id]);
    });

    it('leaves the shared project deal intact', function () {
        $deal = ProjectDeal::factory()->create();
        makeProject('Wedding Elsa', '2026-08-01', ProjectStatus::Draft, $deal->id);
        $ongoing = makeProject('Wedding Elsa', '2026-08-01', ProjectStatus::OnGoing, $deal->id);

        artisan('app:project-duplicate-cleanup --force')
            ->expectsConfirmation('Delete these projects permanently?', 'yes')
            ->assertSuccessful();

        assertDatabaseHas('project_deals', ['id' => $deal->id, 'deleted_at' => null]);
        assertDatabaseHas('projects', ['id' => $ongoing->id, 'project_deal_id' => $deal->id]);
    });

    it('never deletes a group whose rows hold separate deals', function () {
        $deals = ProjectDeal::factory()->count(2)->create();
        $first = makeProject('Wedding Fira', '2026-08-01', ProjectStatus::Draft, $deals[0]->id);
        $second = makeProject('Wedding Fira', '2026-08-01', ProjectStatus::OnGoing, $deals[1]->id);

        // Nothing is deletable, so --force never even reaches the confirmation.
        artisan('app:project-duplicate-cleanup --force')
            ->expectsOutputToContain('REVIEW (separate deals)')
            ->assertSuccessful();

        assertDatabaseHas('projects', ['id' => $first->id, 'project_deal_id' => $deals[0]->id]);
        assertDatabaseHas('projects', ['id' => $second->id, 'project_deal_id' => $deals[1]->id]);
        assertDatabaseHas('project_deals', ['id' => $deals[0]->id, 'deleted_at' => null]);
        assertDatabaseHas('project_deals', ['id' => $deals[1]->id, 'deleted_at' => null]);
    });

    it('restores the deal links when the delete fails', function () {
        $deal = ProjectDeal::factory()->create();
        $draft = makeProject('Wedding Hana', '2026-08-01', ProjectStatus::Draft, $deal->id);
        $ongoing = makeProject('Wedding Hana', '2026-08-01', ProjectStatus::OnGoing, $deal->id);

        $this->mock(ProjectService::class)
            ->shouldReceive('bulkDelete')
            ->once()
            ->andReturn(['error' => true, 'message' => 'boom', 'data' => [], 'code' => 500]);

        artisan('app:project-duplicate-cleanup --force')
            ->expectsConfirmation('Delete these projects permanently?', 'yes')
            ->assertFailed();

        // The null-out shares bulkDelete's transaction, so both rows keep their deal.
        assertDatabaseHas('projects', ['id' => $draft->id, 'project_deal_id' => $deal->id]);
        assertDatabaseHas('projects', ['id' => $ongoing->id, 'project_deal_id' => $deal->id]);
    });

    it('aborts when the confirmation is declined', function () {
        $deal = ProjectDeal::factory()->create();
        $draft = makeProject('Wedding Gita', '2026-08-01', ProjectStatus::Draft, $deal->id);

        makeProject('Wedding Gita', '2026-08-01', ProjectStatus::OnGoing, $deal->id);

        artisan('app:project-duplicate-cleanup --force')
            ->expectsConfirmation('Delete these projects permanently?', 'no')
            ->assertSuccessful();

        assertDatabaseHas('projects', ['id' => $draft->id]);
    });
});
