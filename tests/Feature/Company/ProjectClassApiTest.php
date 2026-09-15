<?php

use App\Models\User;
use Modules\Company\Models\ProjectClass;
use Modules\Production\Models\Project;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
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
