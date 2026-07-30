<?php

use App\Enums\Production\ProjectDealChangeStatus;
use App\Repository\BaseRepository;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDealChange;
use Modules\Production\Repository\ProjectDealChangeRepository;

beforeEach(function () {
    $this->repo = app(ProjectDealChangeRepository::class);

    $this->requester = Employee::factory()->withUser()->create(['name' => 'Sharon'])->refresh();

    $this->withRequester = ProjectDealChange::factory()->create([
        'requested_by' => $this->requester->user_id,
    ]);

    // `requested_by` is nullable, so this row has no `requester` relation at
    // all - it is the negative case for every existence check.
    $this->withoutRequester = ProjectDealChange::factory()->create([
        'requested_by' => null,
    ]);

    $this->bothIds = [$this->withRequester->id, $this->withoutRequester->id];
});

it('is backed by the shared base repository', function () {
    expect($this->repo)->toBeInstanceOf(BaseRepository::class);
});

it('filters on relation existence when whereHas gets only a relation name', function () {
    $ids = $this->repo->get([
        'select' => ['id'],
        'whereIn' => ['id' => $this->bothIds],
        'whereHas' => ['requester'],
    ])->pluck('id')->all();

    expect($ids)->toBe([$this->withRequester->id]);
});

it('filters on relation existence when whereHas maps a relation to null', function () {
    $ids = $this->repo->get([
        'select' => ['id'],
        'whereIn' => ['id' => $this->bothIds],
        'whereHas' => ['requester' => null],
    ])->pluck('id')->all();

    expect($ids)->toBe([$this->withRequester->id]);
});

it('constrains the related query when whereHas maps a relation to a Closure', function () {
    $params = [
        'select' => ['id'],
        'whereIn' => ['id' => $this->bothIds],
    ];

    $matching = $this->repo->get($params + [
        'whereHas' => ['requester' => fn ($query) => $query->where('id', $this->requester->user_id)],
    ])->pluck('id')->all();

    $notMatching = $this->repo->get($params + [
        'whereHas' => ['requester' => fn ($query) => $query->where('id', 0)],
    ]);

    expect($matching)->toBe([$this->withRequester->id])
        ->and($notMatching)->toBeEmpty();
});

it('mixes plain and constrained relations inside one whereHas array', function () {
    $ids = $this->repo->get([
        'select' => ['id'],
        'whereIn' => ['id' => $this->bothIds],
        'whereHas' => [
            'projectDeal',
            'requester' => fn ($query) => $query->where('id', $this->requester->user_id),
        ],
    ])->pluck('id')->all();

    expect($ids)->toBe([$this->withRequester->id]);
});

it('filters and eager-loads with the same constraint via withWhereHas', function () {
    $records = $this->repo->get([
        'select' => ['id', 'requested_by'],
        'whereIn' => ['id' => $this->bothIds],
        'withWhereHas' => ['requester' => fn ($query) => $query->where('id', $this->requester->user_id)],
    ]);

    expect($records->pluck('id')->all())->toBe([$this->withRequester->id])
        ->and($records->first()->relationLoaded('requester'))->toBeTrue()
        ->and($records->first()->requester->id)->toBe($this->requester->user_id);
});

it('widens the result set via orWhereHas', function () {
    $ids = $this->repo->get([
        'select' => ['id'],
        'where' => ['id' => $this->withoutRequester->id],
        'orWhereHas' => ['requester' => fn ($query) => $query->where('id', $this->requester->user_id)],
    ])->pluck('id')->all();

    expect($ids)->toContain($this->withoutRequester->id)
        ->toContain($this->withRequester->id);
});

it('offsets and limits the result set via skip and take', function () {
    $page = $this->repo->get([
        'select' => ['id'],
        'whereIn' => ['id' => $this->bothIds],
        'orderBy' => ['id' => 'asc'],
        'skip' => 1,
        'take' => 1,
    ]);

    expect($page)->toHaveCount(1)
        ->and($page->first()->id)->toBe($this->withoutRequester->id);
});

it('paginates alongside a relation existence check', function () {
    $paginated = $this->repo->paginate([
        'select' => ['id', 'project_deal_id'],
        'whereIn' => ['id' => $this->bothIds],
        'with' => ['projectDeal:id,name,project_date'],
        'whereHas' => ['projectDeal'],
        'orderBy' => ['id' => 'asc'],
    ], 1);

    expect($paginated->total())->toBe(2)
        ->and($paginated->getCollection())->toHaveCount(1)
        ->and($paginated->getCollection()->first()->projectDeal)->not->toBeNull();
});

it('reads, writes and deletes through the base repository contract', function () {
    $created = $this->repo->store([
        'project_deal_id' => $this->withRequester->project_deal_id,
        'requested_by' => $this->requester->user_id,
        'requested_at' => now(),
        'detail_changes' => [
            ['label' => 'Name', 'old_value' => 'Old', 'new_value' => 'New'],
        ],
        'status' => ProjectDealChangeStatus::Pending->value,
    ]);

    $found = $this->repo->show(['where' => ['id' => $created->id]]);
    expect($found)->not->toBeNull()
        ->and($found->detail_changes[0]['label'])->toBe('Name');

    $this->repo->update($found, ['status' => ProjectDealChangeStatus::Approved->value]);
    expect($found->fresh()->status)->toBe(ProjectDealChangeStatus::Approved);

    expect($this->repo->delete($found))->toBeTrue()
        ->and($this->repo->show(['where' => ['id' => $created->id]]))->toBeNull();
});

it('leaves the json detail changes intact when updating unrelated columns', function () {
    $change = $this->repo->show(['where' => ['id' => $this->withRequester->id]]);

    $this->repo->update($change, ['status' => ProjectDealChangeStatus::Approved->value]);

    // toEqual, not toBe: the JSON round-trip preserves the pairs but not the key order.
    expect($change->fresh()->detail_changes)->toEqual($this->withRequester->detail_changes);
});
