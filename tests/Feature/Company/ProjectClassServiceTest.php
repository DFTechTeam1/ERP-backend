<?php

use App\Data\Company\ProjectClass\UpdateStatusData;
use Modules\Company\Models\ProjectClass;
use Modules\Company\Services\ProjectClassService;
use Modules\Production\Models\Project;

use function Pest\Laravel\assertDatabaseHas;
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
    return new ProjectClassService;
}

describe('list', function () {
    it('returns the paginated rows with the aliased columns and the unpaged total', function () {
        ProjectClass::factory()->create(['name' => 'Gold', 'color' => '#FFD700', 'reward' => 50000, 'is_active' => true]);
        ProjectClass::factory()->create(['name' => 'Silver', 'color' => '#C0C0C0', 'reward' => 25000, 'is_active' => false]);

        $response = pcService()->list();

        expect($response['error'])->toBeFalse()
            ->and($response['data']['totalData'])->toBe(2)
            ->and($response['data']['paginated'])->toHaveCount(2);

        $gold = collect($response['data']['paginated'])->firstWhere('name', 'Gold');
        expect($gold->uid)->not->toBeNull()               // id aliased to uid
            ->and($gold->color)->toBe('#FFD700')
            ->and((int) $gold->reward)->toBe(50000)
            ->and((int) $gold->status)->toBe(1);          // is_active aliased to status
    });

    it('filters by name via the search request parameter', function () {
        ProjectClass::factory()->create(['name' => 'Gold']);
        ProjectClass::factory()->create(['name' => 'Silver']);

        request()->merge(['search' => 'gold']);

        $response = pcService()->list();

        expect($response['data']['totalData'])->toBe(1)
            ->and($response['data']['paginated'])->toHaveCount(1)
            ->and($response['data']['paginated']->first()->name)->toBe('Gold');
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
});

describe('update', function () {
    it('updates by id and returns the updated message', function () {
        $class = ProjectClass::factory()->create(['name' => 'Old Name']);

        $response = pcService()->update(['name' => 'New Name', 'color' => '#000', 'reward' => 10], (string) $class->id);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.projectClassUpdated'));

        assertDatabaseHas('project_classes', ['id' => $class->id, 'name' => 'New Name']);
    });

    it('updates via a raw where clause (the path the controller uses)', function () {
        $class = ProjectClass::factory()->create(['name' => 'Before']);

        $response = pcService()->update(['name' => 'After'], 'dummy', 'id = '.$class->id);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_classes', ['id' => $class->id, 'name' => 'After']);
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
