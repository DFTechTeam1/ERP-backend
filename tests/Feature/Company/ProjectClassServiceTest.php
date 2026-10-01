<?php

use App\Data\Company\ProjectClass\UpdateStatusData;
use Modules\Company\Models\ProjectClass;
use Modules\Company\Services\ProjectClassService;
use Modules\Production\Models\Project;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\assertSoftDeleted;

/**
 * Unit-level coverage for ProjectClassService (called directly).
 *
 * Notes on current defects (characterised, not fixed - flagged to the module owner):
 *  - show()   selects a non-existent `uid` column (project_classes has no uid), so it always
 *             returns an error response.
 *  - delete() calls repo->delete() which runs whereIn('id', $scalar) and then ->toArray() on
 *             the returned int, so it always returns an error response.
 * Neither is wired to a working route (the controller show/destroy methods are empty stubs).
 */
function pcService(): ProjectClassService
{
    // Resolve via the container: the service now constructor-injects two repositories.
    return app(ProjectClassService::class);
}

describe('list', function () {
    it('returns only active classes with the list DTO shape (incl. empty pmTiers)', function () {
        ProjectClass::factory()->create(['name' => 'Gold', 'color' => '#FFD700', 'reward' => 50000, 'pm_reward' => 10000, 'vj_reward' => 2000, 'is_active' => true]);
        ProjectClass::factory()->create(['name' => 'Bronze', 'color' => '#CD7F32', 'reward' => 30000, 'is_active' => true]);
        ProjectClass::factory()->create(['name' => 'Silver', 'color' => '#C0C0C0', 'is_active' => false]); // inactive -> excluded

        $response = pcService()->list();

        expect($response['error'])->toBeFalse()
            ->and($response['data']['totalData'])->toBe(2)
            ->and($response['data']['paginated'])->toHaveCount(2);

        $names = collect($response['data']['paginated'])->pluck('name');
        expect($names)->toContain('Gold')
            ->and($names)->toContain('Bronze')
            ->and($names)->not->toContain('Silver');

        $gold = collect($response['data']['paginated'])->firstWhere('name', 'Gold');
        expect($gold->uid)->not->toBeNull()                 // id exposed as uid
            ->and($gold->color)->toBe('#FFD700')
            ->and((int) $gold->reward)->toBe(50000)
            ->and((int) $gold->pm_reward)->toBe(10000)
            ->and((int) $gold->vj_reward)->toBe(2000)
            ->and($gold->is_active)->toBeTrue()
            ->and($gold->pmTiers)->toHaveCount(0);          // no tiers
    });

    it('includes the pm tiers for a tiered class', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class S', 'reward' => 3500000, 'is_active' => true]);
        $class->tiers()->createMany([
            ['pm_count' => 1, 'pm_reward' => 2500000, 'production_reward' => 3500000],
            ['pm_count' => 2, 'pm_reward' => 2500000, 'production_reward' => 4000000],
        ]);

        $response = pcService()->list();
        $row = collect($response['data']['paginated'])->firstWhere('name', 'Class S');

        expect($row->pmTiers)->toHaveCount(2);
        $tier2 = collect($row->pmTiers)->firstWhere('pmCount', 2);
        expect($tier2)->not->toBeNull()
            ->and((int) $tier2->pmReward)->toBe(2500000)
            ->and((int) $tier2->productionReward)->toBe(4000000);
    });

    it('filters by name via the search request parameter', function () {
        ProjectClass::factory()->create(['name' => 'Gold']);
        ProjectClass::factory()->create(['name' => 'Silver']);

        request()->merge(['search' => 'gold']);

        $response = pcService()->list();

        expect($response['data']['totalData'])->toBe(1)
            ->and($response['data']['paginated'])->toHaveCount(1)
            ->and($response['data']['paginated'][0]->name)->toBe('Gold');
    });

    it('returns an empty result when the search matches nothing', function () {
        ProjectClass::factory()->create(['name' => 'Gold']);

        request()->merge(['search' => 'nonexistent-class']);

        $response = pcService()->list();

        expect($response['data']['totalData'])->toBe(0)
            ->and($response['data']['paginated'])->toHaveCount(0);
    });

    it('honours itemsPerPage and page while reporting the full total', function () {
        ProjectClass::factory()->count(3)->create();

        request()->merge(['itemsPerPage' => 2, 'page' => 1]);
        $firstPage = pcService()->list();

        request()->merge(['itemsPerPage' => 2, 'page' => 2]);
        $secondPage = pcService()->list();

        expect($firstPage['data']['paginated'])->toHaveCount(2)
            ->and($secondPage['data']['paginated'])->toHaveCount(1)
            ->and($firstPage['data']['totalData'])->toBe(3)
            ->and($secondPage['data']['totalData'])->toBe(3);
    });
});

describe('getAll', function () {
    it('returns id, name and maximal_point for active classes', function () {
        ProjectClass::factory()->create(['name' => 'Alpha', 'maximal_point' => 10]);
        ProjectClass::factory()->create(['name' => 'Beta', 'maximal_point' => 20]);

        $response = pcService()->getAll();

        expect($response['error'])->toBeFalse()
            ->and($response['data'])->toHaveCount(2)
            ->and($response['data'][0])->toHaveKeys(['id', 'name', 'maximal_point']);
    });

    it('excludes inactive classes', function () {
        ProjectClass::factory()->create(['name' => 'Active One', 'is_active' => true]);
        ProjectClass::factory()->create(['name' => 'Inactive One', 'is_active' => false]);

        $response = pcService()->getAll();
        $names = collect($response['data'])->pluck('name');

        expect($response['data'])->toHaveCount(1)
            ->and($names)->toContain('Active One')
            ->and($names)->not->toContain('Inactive One');
    });
});

describe('store', function () {
    it('creates a project class and defaults maximal_point to 0 when omitted', function () {
        // The Create request no longer collects maximal_point (legacy), so store() defaults it.
        $response = pcService()->store([
            'name' => 'Platinum',
            'color' => '#E5E4E2',
            'reward' => 75000,
        ]);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.projectClassCreated'));

        assertDatabaseHas('project_classes', ['name' => 'Platinum', 'reward' => 75000, 'maximal_point' => 0]);
    });

    it('keeps an explicit maximal_point when one is provided', function () {
        pcService()->store([
            'name' => 'Gold Tier',
            'color' => '#FFD700',
            'reward' => 1000,
            'maximal_point' => 15,
        ]);

        assertDatabaseHas('project_classes', ['name' => 'Gold Tier', 'maximal_point' => 15]);
    });

    it('persists and returns pm_reward and vj_reward', function () {
        $response = pcService()->store([
            'name' => 'Class With Pots',
            'color' => '#111',
            'reward' => 1000000,
            'pm_reward' => 1000000,
            'vj_reward' => 125000,
        ]);

        expect($response['error'])->toBeFalse()
            ->and((float) $response['data']['pm_reward'])->toBe(1000000.0)
            ->and((float) $response['data']['vj_reward'])->toBe(125000.0);

        assertDatabaseHas('project_classes', [
            'name' => 'Class With Pots',
            'pm_reward' => 1000000,
            'vj_reward' => 125000,
        ]);
    });

    it('defaults pm_reward and vj_reward to 0 when omitted', function () {
        pcService()->store([
            'name' => 'No Pots',
            'color' => '#222',
            'reward' => 50000,
        ]);

        assertDatabaseHas('project_classes', ['name' => 'No Pots', 'pm_reward' => 0, 'vj_reward' => 0]);
    });

    it('creates the pm tiers supplied with the class', function () {
        $response = pcService()->store([
            'name' => 'Class S',
            'color' => '#FFB74D',
            'reward' => 3500000,
            'pm_reward' => 2500000,
            'vj_reward' => 350000,
            'pmTiers' => [
                ['pmCount' => 1, 'pmReward' => 2500000, 'productionReward' => 3500000],
                ['pmCount' => 2, 'pmReward' => 2500000, 'productionReward' => 4000000],
                ['pmCount' => 3, 'pmReward' => 3000000, 'productionReward' => 4500000],
            ],
        ]);

        expect($response['error'])->toBeFalse();

        $class = ProjectClass::where('name', 'Class S')->firstOrFail();
        expect($class->tiers()->count())->toBe(3);
        assertDatabaseHas('project_class_pm_tiers', [
            'project_class_id' => $class->id,
            'pm_count' => 2,
            'pm_reward' => 2500000,
            'production_reward' => 4000000,
        ]);
    });
});

describe('update', function () {
    it('updates by id and returns the updated class', function () {
        $class = ProjectClass::factory()->create(['name' => 'Old Name']);

        $response = pcService()->update(['name' => 'New Name', 'color' => '#000', 'reward' => 10], (string) $class->id);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.projectClassUpdated'))
            ->and($response['data']['name'])->toBe('New Name');

        assertDatabaseHas('project_classes', ['id' => $class->id, 'name' => 'New Name']);
    });

    it('returns the class that was updated, not the first active row', function () {
        // Regression guard: update() used to re-fetch the first active class instead of the edited one.
        ProjectClass::factory()->create(['name' => 'First Active']);
        $target = ProjectClass::factory()->create(['name' => 'Target']);

        $response = pcService()->update(['name' => 'Target Renamed', 'color' => '#111', 'reward' => 5], (string) $target->id);

        expect((int) $response['data']['uid'])->toBe($target->id)
            ->and($response['data']['name'])->toBe('Target Renamed');
    });

    it('persists and returns updated pm_reward and vj_reward', function () {
        $class = ProjectClass::factory()->create(['name' => 'Editable', 'reward' => 10, 'pm_reward' => 0, 'vj_reward' => 0]);

        $response = pcService()->update([
            'name' => 'Editable',
            'color' => '#333',
            'reward' => 10,
            'pm_reward' => 2000000,
            'vj_reward' => 200000,
        ], (string) $class->id);

        expect($response['error'])->toBeFalse()
            ->and((float) $response['data']['pm_reward'])->toBe(2000000.0)
            ->and((float) $response['data']['vj_reward'])->toBe(200000.0);

        assertDatabaseHas('project_classes', ['id' => $class->id, 'pm_reward' => 2000000, 'vj_reward' => 200000]);
    });

    it('creates a new tier (no id) on update', function () {
        $class = ProjectClass::factory()->create(['name' => 'Tiered']);

        $response = pcService()->update([
            'name' => 'Tiered',
            'color' => '#000',
            'reward' => 3500000,
            'pmTiers' => [
                ['pmCount' => 2, 'pmReward' => 2500000, 'productionReward' => 4000000],
            ],
        ], (string) $class->id);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_class_pm_tiers', [
            'project_class_id' => $class->id,
            'pm_count' => 2,
            'pm_reward' => 2500000,
            'production_reward' => 4000000,
        ]);
    });

    it('updates an existing tier in place by id', function () {
        $class = ProjectClass::factory()->create(['name' => 'Tiered Edit']);
        $tier = $class->tiers()->create(['pm_count' => 2, 'pm_reward' => 2500000, 'production_reward' => 4000000]);

        pcService()->update([
            'name' => 'Tiered Edit',
            'color' => '#000',
            'reward' => 3500000,
            'pmTiers' => [
                ['id' => $tier->id, 'pmCount' => 2, 'pmReward' => 2700000, 'productionReward' => 4200000],
            ],
        ], (string) $class->id);

        assertDatabaseHas('project_class_pm_tiers', [
            'id' => $tier->id,
            'pm_reward' => 2700000,
            'production_reward' => 4200000,
        ]);
        // updated in place, not duplicated
        expect($class->tiers()->count())->toBe(1);
    });

    it('deletes tiers listed in deletedTierIds', function () {
        $class = ProjectClass::factory()->create(['name' => 'Tiered Delete']);
        $keep = $class->tiers()->create(['pm_count' => 1, 'pm_reward' => 2500000, 'production_reward' => 3500000]);
        $drop = $class->tiers()->create(['pm_count' => 2, 'pm_reward' => 2500000, 'production_reward' => 4000000]);

        pcService()->update([
            'name' => 'Tiered Delete',
            'color' => '#000',
            'reward' => 3500000,
            'deletedTierIds' => [$drop->id],
        ], (string) $class->id);

        assertDatabaseMissing('project_class_pm_tiers', ['id' => $drop->id]);
        assertDatabaseHas('project_class_pm_tiers', ['id' => $keep->id]);
    });
});

describe('updateStatus', function () {
    it('deactivates a class', function () {
        $class = ProjectClass::factory()->create(['is_active' => true]);

        $response = pcService()->updateStatus(UpdateStatusData::from(['status' => false]), $class->id);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_classes', ['id' => $class->id, 'is_active' => 0]);
    });

    it('activates a class', function () {
        $class = ProjectClass::factory()->create(['is_active' => false]);

        pcService()->updateStatus(UpdateStatusData::from(['status' => true]), $class->id);

        assertDatabaseHas('project_classes', ['id' => $class->id, 'is_active' => 1]);
    });
});

describe('bulkDelete', function () {
    it('soft-deletes classes that have no linked project', function () {
        $a = ProjectClass::factory()->create();
        $b = ProjectClass::factory()->create();

        $response = pcService()->bulkDelete([$a->id, $b->id]);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.successDeleteProjectClass'));

        assertSoftDeleted('project_classes', ['id' => $a->id]);
        assertSoftDeleted('project_classes', ['id' => $b->id]);
    });

    it('refuses to delete when a class is linked to a project and deletes nothing', function () {
        $linked = ProjectClass::factory()->create();
        $free = ProjectClass::factory()->create();
        Project::factory()->create(['project_class_id' => $linked->id]);

        $response = pcService()->bulkDelete([$linked->id, $free->id]);

        expect($response['error'])->toBeTrue()
            ->and($response['code'])->toBe(500)
            ->and($response['message'])->toBe(__('global.failedDeleteProjectClassBcsRelation'));

        // nothing was deleted
        assertDatabaseHas('project_classes', ['id' => $linked->id, 'deleted_at' => null]);
        assertDatabaseHas('project_classes', ['id' => $free->id, 'deleted_at' => null]);
    });

    it('returns an error for a non-existent id', function () {
        $response = pcService()->bulkDelete([999999]);

        expect($response['error'])->toBeTrue();
    });
});

describe('known defects (characterisation)', function () {
    it('show() errors because it selects a non-existent uid column', function () {
        $class = ProjectClass::factory()->create();

        $response = pcService()->show((string) $class->id);

        expect($response['error'])->toBeTrue();
    });

    it('delete() errors (whereIn on a scalar + toArray on an int)', function () {
        $class = ProjectClass::factory()->create();

        $response = pcService()->delete($class->id);

        expect($response['error'])->toBeTrue();
    });
});
