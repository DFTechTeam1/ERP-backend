<?php

use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Repository\BaseRepository;
use Modules\Finance\Models\PriceChangeReason;
use Modules\Finance\Models\ProjectDealPriceChange;
use Modules\Finance\Repository\ProjectDealPriceChangeRepository;
use Modules\Production\Models\ProjectDeal;

beforeEach(function () {
    $this->repo = app(ProjectDealPriceChangeRepository::class);

    $this->projectDeal = ProjectDeal::factory()->create();

    $this->withReason = ProjectDealPriceChange::factory()->create([
        'project_deal_id' => $this->projectDeal->id,
        'reason_id' => PriceChangeReason::factory()->create(['name' => 'Client request'])->id,
        'custom_reason' => null,
    ]);

    // `reason_id` is nullable and unconstrained, so this row has no `reason`
    // relation at all - it is the negative case for every existence check.
    $this->withoutReason = ProjectDealPriceChange::factory()->create([
        'project_deal_id' => $this->projectDeal->id,
        'reason_id' => null,
        'custom_reason' => 'Ad hoc discount',
    ]);

    $this->bothIds = [$this->withReason->id, $this->withoutReason->id];
});

it('is backed by the shared base repository', function () {
    expect($this->repo)->toBeInstanceOf(BaseRepository::class);
});

it('filters on relation existence when whereHas gets only a relation name', function () {
    $ids = $this->repo->get([
        'select' => ['id'],
        'whereIn' => ['id' => $this->bothIds],
        'whereHas' => ['reason'],
    ])->pluck('id')->all();

    expect($ids)->toBe([$this->withReason->id]);
});

it('filters on relation existence when whereHas maps a relation to null', function () {
    $ids = $this->repo->get([
        'select' => ['id'],
        'whereIn' => ['id' => $this->bothIds],
        'whereHas' => ['reason' => null],
    ])->pluck('id')->all();

    expect($ids)->toBe([$this->withReason->id]);
});

it('constrains the related query when whereHas maps a relation to a Closure', function () {
    $params = [
        'select' => ['id'],
        'whereIn' => ['id' => $this->bothIds],
    ];

    $matching = $this->repo->get($params + [
        'whereHas' => ['reason' => fn ($query) => $query->where('name', 'Client request')],
    ])->pluck('id')->all();

    $notMatching = $this->repo->get($params + [
        'whereHas' => ['reason' => fn ($query) => $query->where('name', 'Something else')],
    ]);

    expect($matching)->toBe([$this->withReason->id])
        ->and($notMatching)->toBeEmpty();
});

it('mixes plain and constrained relations inside one whereHas array', function () {
    $ids = $this->repo->get([
        'select' => ['id'],
        'whereIn' => ['id' => $this->bothIds],
        'whereHas' => [
            'projectDeal',
            'reason' => fn ($query) => $query->where('name', 'Client request'),
        ],
    ])->pluck('id')->all();

    expect($ids)->toBe([$this->withReason->id]);
});

it('filters and eager-loads with the same constraint via withWhereHas', function () {
    $records = $this->repo->get([
        'select' => ['id', 'reason_id'],
        'whereIn' => ['id' => $this->bothIds],
        'withWhereHas' => ['reason' => fn ($query) => $query->where('name', 'Client request')],
    ]);

    expect($records->pluck('id')->all())->toBe([$this->withReason->id])
        ->and($records->first()->relationLoaded('reason'))->toBeTrue()
        ->and($records->first()->reason->name)->toBe('Client request');
});

it('widens the result set via orWhereHas', function () {
    $ids = $this->repo->get([
        'select' => ['id'],
        'where' => ['id' => $this->withoutReason->id],
        'orWhereHas' => ['reason' => fn ($query) => $query->where('name', 'Client request')],
    ])->pluck('id')->all();

    expect($ids)->toContain($this->withoutReason->id)
        ->toContain($this->withReason->id);
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
        ->and($page->first()->id)->toBe($this->withoutReason->id);
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
        ->and($paginated->getCollection()->first()->projectDeal->name)->toBe($this->projectDeal->name);
});

it('reads, writes and deletes through the base repository contract', function () {
    $created = $this->repo->store([
        'project_deal_id' => $this->projectDeal->id,
        'old_price' => 1000,
        'new_price' => 2000,
        'custom_reason' => 'Scope added',
        'requested_by' => $this->withReason->requested_by,
        'requested_at' => now(),
        'status' => ProjectDealChangePriceStatus::Pending->value,
    ]);

    $found = $this->repo->show(['where' => ['id' => $created->id]]);
    expect($found)->not->toBeNull();

    $this->repo->update($found, ['new_price' => 3000]);
    expect((float) $found->fresh()->new_price)->toBe(3000.0);

    expect($this->repo->delete($found))->toBeTrue()
        ->and($this->repo->show(['where' => ['id' => $created->id]]))->toBeNull();
});
