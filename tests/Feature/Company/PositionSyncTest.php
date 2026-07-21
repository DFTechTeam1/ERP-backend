<?php

use App\Enums\Employee\Status;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Models\PositionBackup;
use Modules\Company\Models\Setting;
use Modules\Hrd\Models\Employee;

/**
 * A small Greatday position tree:
 *   root(2) → division "Production"(10) → positions 100/101/102
 * plus whatever extra nodes a test appends.
 *
 * @param  array<int, array<string, mixed>>  $extra
 * @return array<int, array<string, mixed>>
 */
function greatdayPositionTree(array $extra = []): array
{
    return array_merge([
        ['positionId' => 2, 'posCode' => 'ROOT', 'parentId' => 0, 'posNameEn' => 'DFactory Visual', 'parentPath' => '0'],
        ['positionId' => 10, 'posCode' => 'DIV-PROD', 'parentId' => 2, 'posNameEn' => 'Production', 'parentPath' => '0,2'],
        ['positionId' => 100, 'posCode' => 'POS-3D', 'parentId' => 10, 'posNameEn' => '3D Artist', 'parentPath' => '0,2,10'],
        ['positionId' => 101, 'posCode' => 'POS-ANIM', 'parentId' => 10, 'posNameEn' => 'Animator', 'parentPath' => '0,2,10'],
        ['positionId' => 102, 'posCode' => 'POS-COMP', 'parentId' => 10, 'posNameEn' => 'Senior Compositor', 'parentPath' => '0,2,10'],
    ], $extra);
}

/**
 * @param  array<int, array<string, mixed>>  $tree
 */
function fakeGreatdayPositions(array $tree): void
{
    Http::fake([
        '*company/position' => Http::response([
            'page' => 1,
            'limit' => 100,
            'total' => count($tree),
            'totalPage' => 1,
            'data' => $tree,
        ], 200),
        '*' => Http::response(['access_token' => 'x', 'refresh_token' => 'y'], 200),
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    config(['app.greatday.base_url' => 'https://greatday.test/api']);
});

it('classifies new, changed and gone positions', function () {
    fakeGreatdayPositions(greatdayPositionTree());

    $division = DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 10]);

    // matches Greatday 100 exactly -> no change
    PositionBackup::create(['name' => '3D Artist', 'division_id' => $division->id, 'greatday_code' => 'POS-3D', 'greatday_position_id' => 100]);
    // Greatday renamed 102 to "Senior Compositor" -> changed
    PositionBackup::create(['name' => 'Compositor', 'division_id' => $division->id, 'greatday_code' => 'POS-COMP', 'greatday_position_id' => 102]);
    // no longer in Greatday -> gone
    $gone = PositionBackup::create(['name' => 'Retired Role', 'division_id' => $division->id, 'greatday_code' => 'POS-OLD', 'greatday_position_id' => 999]);

    $response = $this->getJson(route('api.greatday.positions.preview'));

    $response->assertStatus(201);
    $data = $response->json('data');

    expect(collect($data['new'])->pluck('greatday_position_id'))->toContain(101)
        ->and(collect($data['new'])->pluck('greatday_position_id'))->not->toContain(100);

    expect(collect($data['changed'])->pluck('greatday_position_id'))->toContain(102);

    expect(collect($data['gone'])->pluck('erp_uid'))->toContain($gone->uid);
});

it('marks a gone position as non-deletable when an active employee still uses it', function () {
    fakeGreatdayPositions(greatdayPositionTree());

    $division = DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 10]);

    $freeToDelete = PositionBackup::create(['name' => 'Empty Role', 'division_id' => $division->id, 'greatday_code' => 'POS-EMPTY', 'greatday_position_id' => 900]);
    $inUse = PositionBackup::create(['name' => 'Busy Role', 'division_id' => $division->id, 'greatday_code' => 'POS-BUSY', 'greatday_position_id' => 901]);

    Employee::factory()->create(['position_id' => $inUse->id, 'status' => Status::Permanent->value]);

    $response = $this->getJson(route('api.greatday.positions.preview'));
    $gone = collect($response->json('data.gone'))->keyBy('erp_uid');

    expect($gone[$freeToDelete->uid]['deletable'])->toBeTrue();
    expect($gone[$inUse->uid]['deletable'])->toBeFalse();
    expect($gone[$inUse->uid]['references']['employees'])->toBe(1);
});

it('does not flag an unlinked position as new when its posCode matches (self-healing)', function () {
    fakeGreatdayPositions(greatdayPositionTree());

    $division = DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 10]);
    // legacy row: no greatday_position_id yet, only the code
    PositionBackup::create(['name' => '3D Artist', 'division_id' => $division->id, 'greatday_code' => 'POS-3D', 'greatday_position_id' => null]);

    $data = $this->getJson(route('api.greatday.positions.preview'))->json('data');

    expect(collect($data['new'])->pluck('greatday_position_id'))->not->toContain(100);
    expect(collect($data['gone'])->pluck('name'))->not->toContain('3D Artist');
});

it('creates a confirmed new position', function () {
    fakeGreatdayPositions(greatdayPositionTree());
    DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 10]);

    $response = $this->postJson(route('api.greatday.positions.sync'), [
        'create' => [101],
        'update' => [],
        'delete' => [],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('position_backups', [
        'greatday_position_id' => 101,
        'name' => 'Animator',
        'greatday_code' => 'POS-ANIM',
    ]);
});

it('updates a confirmed changed position', function () {
    fakeGreatdayPositions(greatdayPositionTree());
    $division = DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 10]);
    $position = PositionBackup::create(['name' => 'Compositor', 'division_id' => $division->id, 'greatday_code' => 'POS-COMP', 'greatday_position_id' => 102]);

    $this->postJson(route('api.greatday.positions.sync'), [
        'create' => [],
        'update' => [102],
        'delete' => [],
    ])->assertStatus(201);

    $this->assertDatabaseHas('position_backups', [
        'id' => $position->id,
        'name' => 'Senior Compositor',
    ]);
});

it('archives a gone position with no references and rejects one still in use', function () {
    fakeGreatdayPositions(greatdayPositionTree());
    $division = DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 10]);

    $deletable = PositionBackup::create(['name' => 'Empty Role', 'division_id' => $division->id, 'greatday_code' => 'POS-EMPTY', 'greatday_position_id' => 900]);
    $blocked = PositionBackup::create(['name' => 'Busy Role', 'division_id' => $division->id, 'greatday_code' => 'POS-BUSY', 'greatday_position_id' => 901]);
    Employee::factory()->create(['position_id' => $blocked->id, 'status' => Status::Permanent->value]);

    $response = $this->postJson(route('api.greatday.positions.sync'), [
        'create' => [],
        'update' => [],
        'delete' => [$deletable->uid, $blocked->uid],
    ]);

    $response->assertStatus(201);
    $result = $response->json('data');

    expect($result['deleted'])->toContain($deletable->uid);
    expect(collect($result['rejected'])->pluck('erp_uid'))->toContain($blocked->uid);

    $this->assertSoftDeleted('position_backups', ['id' => $deletable->id]);
    $this->assertDatabaseHas('position_backups', ['id' => $blocked->id, 'deleted_at' => null]);
});

it('refuses to delete a position pinned in a settings key', function () {
    fakeGreatdayPositions(greatdayPositionTree());
    $division = DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 10]);
    $pinned = PositionBackup::create(['name' => 'Director', 'division_id' => $division->id, 'greatday_code' => 'POS-DIR', 'greatday_position_id' => 902]);

    Setting::create(['key' => 'position_as_directors', 'value' => json_encode([$pinned->uid])]);
    Cache::forget('setting');

    $response = $this->postJson(route('api.greatday.positions.sync'), [
        'create' => [],
        'update' => [],
        'delete' => [$pinned->uid],
    ]);

    $response->assertStatus(201);
    $rejected = collect($response->json('data.rejected'))->firstWhere('erp_uid', $pinned->uid);

    expect($rejected)->not->toBeNull();
    expect($rejected['references']['settings'])->toContain('position_as_directors');
    $this->assertDatabaseHas('position_backups', ['id' => $pinned->id, 'deleted_at' => null]);
});
