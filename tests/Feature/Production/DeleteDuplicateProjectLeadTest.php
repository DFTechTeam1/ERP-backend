<?php

use App\Console\Commands\Cleanup\DeleteDuplicateProjectLead;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectLead;
use Modules\Production\Models\ProjectLeadFollowUp;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * The command's internals are protected, so bind a closure to an instance to
 * exercise detection and classification directly.
 */
function invokeOnLeadCommand(string $method, mixed ...$arguments): mixed
{
    $command = new DeleteDuplicateProjectLead;

    return Closure::bind(
        fn () => $this->{$method}(...$arguments),
        $command,
        DeleteDuplicateProjectLead::class
    )();
}

/**
 * Detection scans the whole project_leads table, which may already hold rows
 * this test did not create, so results are narrowed to the names under test.
 *
 * @param  array<int, string>  $names
 * @return Collection<int, Illuminate\Database\Eloquent\Collection<int, ProjectLead>>
 */
function duplicateLeadGroupsFor(array $names): Collection
{
    return invokeOnLeadCommand('getDuplicateLeadGroups')
        ->values()
        ->filter(fn ($group) => in_array($group->first()->name, $names, true))
        ->values();
}

/**
 * @return array<int, int> lead ids of one group, sorted
 */
function duplicateLeadIdsFor(string $name): array
{
    return duplicateLeadGroupsFor([$name])
        ->flatMap(fn ($group) => $group->pluck('id'))
        ->sort()->values()->all();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeLead(string $name, string $date, array $attributes = []): ProjectLead
{
    return ProjectLead::factory()->create(array_merge([
        'name' => $name,
        'project_date' => $date,
    ], $attributes));
}

function makeFollowUp(ProjectLead $lead): ProjectLeadFollowUp
{
    return ProjectLeadFollowUp::create([
        'project_lead_id' => $lead->id,
        'follow_up_date' => date('Y-m-d'),
        'customer_phone' => '628123456789',
        'message' => 'Following up the quotation',
    ]);
}

describe('detection', function () {
    it('flags leads sharing a name on the same project date', function () {
        $first = makeLead('Wedding Rina & Adi', '2026-08-01');
        $second = makeLead('Wedding Rina & Adi', '2026-08-01');

        expect(duplicateLeadIdsFor('Wedding Rina & Adi'))->toBe([$first->id, $second->id]);
    });

    it('does not flag the same name on different project dates', function () {
        makeLead('Gala Dinner Astra', '2026-08-01');
        makeLead('Gala Dinner Astra', '2026-09-15');

        expect(duplicateLeadIdsFor('Gala Dinner Astra'))->toBe([]);
    });

    it('does not flag different names on the same project date', function () {
        makeLead('Gala Dinner Astra', '2026-08-01');
        makeLead('Gala Dinner Toyota', '2026-08-01');

        expect(duplicateLeadIdsFor('Gala Dinner Astra'))->toBe([]);
    });

    it('keeps duplicate groups separated per name and date pair', function () {
        makeLead('Event A', '2026-08-01');
        makeLead('Event A', '2026-08-01');
        makeLead('Event B', '2026-08-02');
        makeLead('Event B', '2026-08-02');

        // Shares a name with group A and a date with group B, but neither pair.
        makeLead('Event A', '2026-08-02');

        expect(duplicateLeadGroupsFor(['Event A', 'Event B'])->count())->toBe(2);
    });
});

describe('classification', function () {
    it('never deletes rows holding separate deals', function () {
        $deals = ProjectDeal::factory()->count(2)->create();
        makeLead('Wedding Sinta', '2026-08-01', ['project_deal_id' => $deals[0]->id]);
        makeLead('Wedding Sinta', '2026-08-01', ['project_deal_id' => $deals[1]->id]);

        $result = invokeOnLeadCommand('classifyDuplicateGroup', duplicateLeadGroupsFor(['Wedding Sinta'])->first());

        expect($result['verdict'])->toBe('review')
            ->and($result['keep'])->toBeNull()
            ->and($result['delete'])->toBeEmpty();
    });

    it('keeps the lead linked to a project deal even when it is the emptiest copy', function () {
        $deal = ProjectDeal::factory()->create();
        $rich = makeLead('Wedding Bella', '2026-08-01');
        $linked = ProjectLead::factory()->bare()->create([
            'name' => 'Wedding Bella',
            'project_date' => '2026-08-01',
            'project_deal_id' => $deal->id,
        ]);

        $result = invokeOnLeadCommand('classifyDuplicateGroup', duplicateLeadGroupsFor(['Wedding Bella'])->first());

        expect($result['verdict'])->toBe('duplicate')
            ->and($result['keep']->id)->toBe($linked->id)
            ->and($result['delete']->pluck('id')->all())->toBe([$rich->id]);
    });

    it('keeps the copy holding the most complete data', function () {
        $bare = ProjectLead::factory()->bare()->create([
            'name' => 'Corporate Gathering BCA',
            'project_date' => '2026-08-01',
        ]);
        $complete = makeLead('Corporate Gathering BCA', '2026-08-01');

        $result = invokeOnLeadCommand('classifyDuplicateGroup', duplicateLeadGroupsFor(['Corporate Gathering BCA'])->first());

        expect($result['keep']->id)->toBe($complete->id)
            ->and($result['delete']->pluck('id')->all())->toBe([$bare->id]);
    });

    it('deletes the newest copy when every column matches', function () {
        $shared = ProjectLead::factory()->raw([
            'name' => 'Sound Check Nusantara',
            'project_date' => '2026-08-01',
        ]);

        $ids = collect(range(1, 4))
            ->map(fn () => ProjectLead::factory()->create(array_merge($shared, ['uid' => Str::uuid()->toString()]))->id);

        $result = invokeOnLeadCommand('classifyDuplicateGroup', duplicateLeadGroupsFor(['Sound Check Nusantara'])->first());

        expect($result['keep']->id)->toBe($ids->min())
            ->and($result['delete']->pluck('id')->sort()->values()->all())
            ->toBe($ids->reject(fn (int $id) => $id === $ids->min())->sort()->values()->all());
    });

    it('breaks a completeness tie on the copy carrying follow up history', function () {
        $bare = ProjectLead::factory()->bare()->create([
            'name' => 'Launching Produk X',
            'project_date' => '2026-08-01',
        ]);
        $followedUp = ProjectLead::factory()->bare()->create([
            'name' => 'Launching Produk X',
            'project_date' => '2026-08-01',
        ]);

        makeFollowUp($followedUp);
        makeFollowUp($followedUp);

        $result = invokeOnLeadCommand('classifyDuplicateGroup', duplicateLeadGroupsFor(['Launching Produk X'])->first());

        expect($result['keep']->id)->toBe($followedUp->id)
            ->and($result['delete']->pluck('id')->all())->toBe([$bare->id]);
    });

    it('ranks a cancelled copy below an active one', function () {
        $cancelled = ProjectLead::factory()->cancelled()->create([
            'name' => 'Product Launch Wuling',
            'project_date' => '2026-08-01',
        ]);
        $active = ProjectLead::factory()->bare()->create([
            'name' => 'Product Launch Wuling',
            'project_date' => '2026-08-01',
        ]);

        $result = invokeOnLeadCommand('classifyDuplicateGroup', duplicateLeadGroupsFor(['Product Launch Wuling'])->first());

        expect($result['keep']->id)->toBe($active->id)
            ->and($result['delete']->pluck('id')->all())->toBe([$cancelled->id]);
    });
});

describe('test named leads', function () {
    it('matches the junk words whatever the casing or surrounding text', function (string $name) {
        expect(invokeOnLeadCommand('hasJunkName', $name))->toBeTrue();
    })->with([
        'test',
        'Test',
        'TESTING',
        'test 2',
        'testing3',
        'Dummy',
        'Lead dummy',
        'test wedding rina',
    ]);

    it('leaves real client names alone', function (string $name) {
        expect(invokeOnLeadCommand('hasJunkName', $name))->toBeFalse();
    })->with([
        'Latest Project',
        'Testimoni Client',
        'Protest Gathering',
        'Wedding Rina & Adi',
        'Dummies Corporate',
    ]);

    it('deletes a test named lead even when it has no duplicate', function () {
        $junk = makeLead('test wedding', '2026-08-01');
        $real = makeLead('Wedding Ayu', '2026-08-01');

        artisan('app:delete-duplicate-project-lead --force')
            ->expectsConfirmation('Delete these project leads permanently?', 'yes')
            ->assertSuccessful();

        assertDatabaseMissing('project_leads', ['id' => $junk->id]);
        assertDatabaseHas('project_leads', ['id' => $real->id]);
    });

    it('never deletes a test named lead linked to a project deal', function () {
        $deal = ProjectDeal::factory()->create();
        $linked = makeLead('dummy lead', '2026-08-01', ['project_deal_id' => $deal->id]);
        $plain = makeLead('dummy lead 2', '2026-08-02');

        artisan('app:delete-duplicate-project-lead --force')
            ->expectsOutputToContain('REVIEW (linked to a deal)')
            ->expectsConfirmation('Delete these project leads permanently?', 'yes')
            ->assertSuccessful();

        assertDatabaseHas('project_leads', ['id' => $linked->id, 'project_deal_id' => $deal->id]);
        assertDatabaseMissing('project_leads', ['id' => $plain->id]);
    });

    it('removes the follow ups of a deleted test named lead', function () {
        $junk = makeLead('testing lead', '2026-08-01');
        $followUp = makeFollowUp($junk);

        artisan('app:delete-duplicate-project-lead --force')
            ->expectsConfirmation('Delete these project leads permanently?', 'yes')
            ->assertSuccessful();

        assertDatabaseMissing('project_lead_follow_ups', ['id' => $followUp->id]);
    });

    it('deletes every copy of a test named duplicate instead of keeping a survivor', function () {
        $first = makeLead('test event', '2026-08-01');
        $second = makeLead('test event', '2026-08-01');

        artisan('app:delete-duplicate-project-lead --force')
            ->expectsConfirmation('Delete these project leads permanently?', 'yes')
            ->assertSuccessful();

        assertDatabaseMissing('project_leads', ['id' => $first->id]);
        assertDatabaseMissing('project_leads', ['id' => $second->id]);
    });

    it('reports nothing to do when the table holds no duplicate or test named lead', function () {
        ProjectLead::query()->delete();

        makeLead('Wedding Ayu', '2026-08-01');

        artisan('app:delete-duplicate-project-lead')
            ->expectsOutputToContain('No duplicate or test named project leads found.')
            ->assertSuccessful();
    });
});

describe('dry run', function () {
    it('deletes nothing without --force', function () {
        $first = makeLead('Wedding Clara', '2026-08-01');
        $second = makeLead('Wedding Clara', '2026-08-01');

        artisan('app:delete-duplicate-project-lead')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        assertDatabaseHas('project_leads', ['id' => $first->id]);
        assertDatabaseHas('project_leads', ['id' => $second->id]);
    });
});

describe('cleanup', function () {
    it('deletes the redundant copies and keeps the survivor', function () {
        $keep = makeLead('Wedding Dinda', '2026-08-01');
        $newest = ProjectLead::factory()->bare()->create([
            'name' => 'Wedding Dinda',
            'project_date' => '2026-08-01',
        ]);

        artisan('app:delete-duplicate-project-lead --force')
            ->expectsConfirmation('Delete these project leads permanently?', 'yes')
            ->assertSuccessful();

        assertDatabaseMissing('project_leads', ['id' => $newest->id]);
        assertDatabaseHas('project_leads', ['id' => $keep->id]);
    });

    it('removes the follow ups of the deleted copies only', function () {
        $keep = makeLead('Wedding Elsa', '2026-08-01');
        $drop = ProjectLead::factory()->bare()->create([
            'name' => 'Wedding Elsa',
            'project_date' => '2026-08-01',
        ]);

        $keptFollowUp = makeFollowUp($keep);
        $droppedFollowUp = makeFollowUp($drop);

        artisan('app:delete-duplicate-project-lead --force')
            ->expectsConfirmation('Delete these project leads permanently?', 'yes')
            ->assertSuccessful();

        assertDatabaseHas('project_lead_follow_ups', ['id' => $keptFollowUp->id]);
        assertDatabaseMissing('project_lead_follow_ups', ['id' => $droppedFollowUp->id]);
    });

    it('leaves the linked project deal intact', function () {
        $deal = ProjectDeal::factory()->create();
        $keep = makeLead('Wedding Fira', '2026-08-01', ['project_deal_id' => $deal->id]);
        $drop = makeLead('Wedding Fira', '2026-08-01');

        artisan('app:delete-duplicate-project-lead --force')
            ->expectsConfirmation('Delete these project leads permanently?', 'yes')
            ->assertSuccessful();

        assertDatabaseMissing('project_leads', ['id' => $drop->id]);
        assertDatabaseHas('project_leads', ['id' => $keep->id, 'project_deal_id' => $deal->id]);
        assertDatabaseHas('project_deals', ['id' => $deal->id, 'deleted_at' => null]);
    });

    it('never deletes a group whose rows hold separate deals', function () {
        $deals = ProjectDeal::factory()->count(2)->create();
        $first = makeLead('Wedding Gita', '2026-08-01', ['project_deal_id' => $deals[0]->id]);
        $second = makeLead('Wedding Gita', '2026-08-01', ['project_deal_id' => $deals[1]->id]);

        // Nothing is deletable, so --force never even reaches the confirmation.
        artisan('app:delete-duplicate-project-lead --force')
            ->expectsOutputToContain('REVIEW (separate deals)')
            ->assertSuccessful();

        assertDatabaseHas('project_leads', ['id' => $first->id, 'project_deal_id' => $deals[0]->id]);
        assertDatabaseHas('project_leads', ['id' => $second->id, 'project_deal_id' => $deals[1]->id]);
    });

    it('aborts when the confirmation is declined', function () {
        $first = makeLead('Wedding Hana', '2026-08-01');
        $second = ProjectLead::factory()->bare()->create([
            'name' => 'Wedding Hana',
            'project_date' => '2026-08-01',
        ]);

        artisan('app:delete-duplicate-project-lead --force')
            ->expectsConfirmation('Delete these project leads permanently?', 'no')
            ->assertSuccessful();

        assertDatabaseHas('project_leads', ['id' => $first->id]);
        assertDatabaseHas('project_leads', ['id' => $second->id]);
    });
});
