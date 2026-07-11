<?php

use App\Repository\BaseRepository;
use Modules\Company\Models\City;

function cityRepository(): BaseRepository
{
    return new class(new City) extends BaseRepository {};
}

it('filters records with the whereIn param', function () {
    $cities = City::factory()->count(3)->create();
    $wanted = $cities->take(2);

    $result = cityRepository()->get([
        'whereIn' => ['id' => $wanted->pluck('id')->all()],
    ]);

    expect($result->pluck('id')->sort()->values()->all())
        ->toEqual($wanted->pluck('id')->sort()->values()->all());
});

it('returns nothing when the whereIn set is empty', function () {
    City::factory()->count(2)->create();

    $result = cityRepository()->get([
        'whereIn' => ['id' => []],
    ]);

    expect($result)->toBeEmpty();
});

it('limits columns with the select param', function () {
    $city = City::factory()->create();

    $result = cityRepository()->get([
        'whereIn' => ['id' => [$city->id]],
        'select' => ['id'],
    ]);

    $attributes = $result->first()->getAttributes();

    expect(array_keys($attributes))->toEqual(['id']);
});
