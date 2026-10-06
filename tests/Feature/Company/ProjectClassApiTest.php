<?php

use App\Models\User;
use Modules\Company\Models\ProjectClass;
use Modules\Production\Models\Project;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\assertSoftDeleted;

/**
 * End-to-end (HTTP) coverage for the project class API.
 *
 * Wired endpoints (via ProjectClassController):
 *   GET    /api/projectClass            -> index (list)
 *   GET    /api/projectClass/getAll     -> getAll
 *   POST   /api/projectClass            -> store   (Create request)
 *   PUT    /api/projectClass/{id}       -> update  (Update request)
 *   PUT    /api/projectClass/{id}/status-> updateStatus (UpdateStatusData)
 *   POST   /api/projectClass/bulk       -> bulkDelete
 *   GET    /api/projectClass/{id}       -> show    (empty stub)
 *   DELETE /api/projectClass/{id}       -> destroy (empty stub)
 *
 * Note: generalResponse() defaults to code 201, and apiResponse() uses that code as the HTTP
 * status - so successful reads/writes here return 201 (not 200).
 */
beforeEach(function () {
    actingAs(User::factory()->create());
});

describe('GET /api/projectClass', function () {
    it('lists project classes with pagination metadata', function () {
        ProjectClass::factory()->count(3)->create();

        $response = $this->getJson('/api/projectClass');

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['paginated', 'totalData']])
            ->assertJsonPath('data.totalData', 3);
    });

    it('filters the list by the search query', function () {
        ProjectClass::factory()->create(['name' => 'Gold']);
        ProjectClass::factory()->create(['name' => 'Silver']);

        $response = $this->getJson('/api/projectClass?search=gold');

        $response->assertStatus(201)
            ->assertJsonPath('data.totalData', 1);
    });
});

describe('GET /api/projectClass/getAll', function () {
    it('returns all classes', function () {
        ProjectClass::factory()->count(2)->create();

        $this->getJson('/api/projectClass/getAll')
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => [['id', 'name', 'maximal_point']]]);
    });
});

describe('POST /api/projectClass', function () {
    it('creates a project class (maximal_point defaults to 0)', function () {
        $this->postJson('/api/projectClass', [
            'name' => 'Platinum',
            'color' => '#E5E4E2',
            'reward' => 90000,
        ])->assertStatus(201);

        assertDatabaseHas('project_classes', ['name' => 'Platinum', 'reward' => 90000, 'maximal_point' => 0]);
    });

    it('rejects a payload missing required fields', function () {
        $this->postJson('/api/projectClass', ['name' => 'Incomplete'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color', 'reward']);
    });

    it('rejects a duplicate name', function () {
        ProjectClass::factory()->create(['name' => 'Existing']);

        $this->postJson('/api/projectClass', [
            'name' => 'Existing',
            'color' => '#111',
            'reward' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);
    });
});

describe('PUT /api/projectClass/{id}', function () {
    it('updates a project class', function () {
        $class = ProjectClass::factory()->create(['name' => 'Old', 'color' => '#000', 'reward' => 1]);

        $this->putJson("/api/projectClass/{$class->id}", [
            'name' => 'Renamed',
            'color' => '#FFF',
            'reward' => 12345,
        ])->assertStatus(201);

        assertDatabaseHas('project_classes', ['id' => $class->id, 'name' => 'Renamed', 'reward' => 12345]);
    });

    it('allows keeping the same name (unique rule ignores itself)', function () {
        $class = ProjectClass::factory()->create(['name' => 'Keeper']);

        $this->putJson("/api/projectClass/{$class->id}", [
            'name' => 'Keeper',
            'color' => '#FFF',
            'reward' => 500,
        ])->assertStatus(201);
    });

    it('rejects renaming to another class name', function () {
        ProjectClass::factory()->create(['name' => 'Taken']);
        $class = ProjectClass::factory()->create(['name' => 'Mine']);

        $this->putJson("/api/projectClass/{$class->id}", [
            'name' => 'Taken',
            'color' => '#FFF',
            'reward' => 500,
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);
    });

    it('rejects an update missing required fields', function () {
        $class = ProjectClass::factory()->create();

        $this->putJson("/api/projectClass/{$class->id}", ['name' => 'OnlyName'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color', 'reward']);
    });
});

describe('PUT /api/projectClass/{id}/status', function () {
    it('deactivates a class', function () {
        $class = ProjectClass::factory()->create(['is_active' => true]);

        $this->putJson("/api/projectClass/{$class->id}/status", ['status' => false])
            ->assertStatus(201);

        assertDatabaseHas('project_classes', ['id' => $class->id, 'is_active' => 0]);
    });

    it('activates a class', function () {
        $class = ProjectClass::factory()->create(['is_active' => false]);

        $this->putJson("/api/projectClass/{$class->id}/status", ['status' => true])
            ->assertStatus(201);

        assertDatabaseHas('project_classes', ['id' => $class->id, 'is_active' => 1]);
    });

    it('rejects a missing status', function () {
        $class = ProjectClass::factory()->create();

        $this->putJson("/api/projectClass/{$class->id}/status", [])
            ->assertStatus(422);
    });
});

describe('POST /api/projectClass/bulk', function () {
    it('soft-deletes classes that have no linked project', function () {
        $a = ProjectClass::factory()->create();
        $b = ProjectClass::factory()->create();

        $this->postJson('/api/projectClass/bulk', [
            'ids' => [['uid' => $a->id], ['uid' => $b->id]],
        ])->assertStatus(201);

        assertSoftDeleted('project_classes', ['id' => $a->id]);
        assertSoftDeleted('project_classes', ['id' => $b->id]);
    });

    it('returns a 500 error when a class is linked to a project', function () {
        $linked = ProjectClass::factory()->create();
        Project::factory()->create(['project_class_id' => $linked->id]);

        $this->postJson('/api/projectClass/bulk', [
            'ids' => [['uid' => $linked->id]],
        ])->assertStatus(500);

        assertDatabaseHas('project_classes', ['id' => $linked->id, 'deleted_at' => null]);
    });
});

describe('pm tiers (e2e)', function () {
    it('creates a class together with its pm tiers', function () {
        $this->postJson('/api/projectClass', [
            'name' => 'Class S',
            'color' => '#FFB74D',
            'reward' => 3500000,
            'pm_reward' => 2500000,
            'vj_reward' => 350000,
            'pmTiers' => [
                ['pmCount' => 1, 'pmReward' => 2500000, 'productionReward' => 3500000, 'leadReward' => 2500000, 'supportReward' => 0],
                ['pmCount' => 2, 'pmReward' => 2500000, 'productionReward' => 4000000, 'leadReward' => 1750000, 'supportReward' => 750000],
                ['pmCount' => 3, 'pmReward' => 3000000, 'productionReward' => 4500000, 'leadReward' => 1500000, 'supportReward' => 750000],
            ],
        ])->assertStatus(201);

        $class = ProjectClass::where('name', 'Class S')->firstOrFail();
        expect($class->tiers()->count())->toBe(3);
        assertDatabaseHas('project_class_pm_tiers', [
            'project_class_id' => $class->id,
            'pm_count' => 3,
            'pm_reward' => 3000000,
            'production_reward' => 4500000,
            'lead_reward' => 1500000,
            'support_reward' => 750000,
        ]);
    });

    it('adds, updates and deletes tiers in a single update', function () {
        $class = ProjectClass::factory()->create(['name' => 'Tiered']);
        $existing = $class->tiers()->create(['pm_count' => 1, 'pm_reward' => 2500000, 'production_reward' => 3500000]);
        $toDelete = $class->tiers()->create(['pm_count' => 3, 'pm_reward' => 3000000, 'production_reward' => 4500000]);

        $this->putJson("/api/projectClass/{$class->id}", [
            'name' => 'Tiered',
            'color' => '#FFB74D',
            'reward' => 3500000,
            'pmTiers' => [
                ['id' => $existing->id, 'pmCount' => 1, 'pmReward' => 2600000, 'productionReward' => 3600000, 'leadReward' => 2600000, 'supportReward' => 0], // update existing
                ['pmCount' => 2, 'pmReward' => 2500000, 'productionReward' => 4000000, 'leadReward' => 1750000, 'supportReward' => 750000],                   // create new
            ],
            'deletedTierIds' => [$toDelete->id],                                                               // delete
        ])->assertStatus(201);

        assertDatabaseHas('project_class_pm_tiers', ['id' => $existing->id, 'pm_reward' => 2600000, 'production_reward' => 3600000]);
        assertDatabaseHas('project_class_pm_tiers', ['project_class_id' => $class->id, 'pm_count' => 2, 'production_reward' => 4000000]);
        assertDatabaseMissing('project_class_pm_tiers', ['id' => $toDelete->id]);
    });

    it('exposes pmTiers in the list payload', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class S List']);
        $class->tiers()->create(['pm_count' => 2, 'pm_reward' => 2500000, 'production_reward' => 4000000, 'lead_reward' => 1750000, 'support_reward' => 750000]);

        $this->getJson('/api/projectClass')
            ->assertStatus(201)
            ->assertJsonPath('data.paginated.0.pmTiers.0.pmCount', 2)
            ->assertJsonStructure([
                'data' => ['paginated' => [['pmTiers' => [['pmCount', 'pmReward', 'productionReward', 'leadReward', 'supportReward']]]]],
            ]);
    });
});

describe('resource stubs', function () {
    it('show endpoint returns an empty payload (unimplemented stub)', function () {
        $class = ProjectClass::factory()->create();

        $this->getJson("/api/projectClass/{$class->id}")
            ->assertOk()
            ->assertExactJson([]);
    });

    it('destroy endpoint returns an empty payload (unimplemented stub)', function () {
        $class = ProjectClass::factory()->create();

        $this->deleteJson("/api/projectClass/{$class->id}")
            ->assertOk()
            ->assertExactJson([]);
    });
});
