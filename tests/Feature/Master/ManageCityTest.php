<?php

beforeEach(function () {
    $this->user = initAuthenticateUser(permissions: [
        'create_city',
        'delete_city',
        'create_country',
        'create_state'
    ]);

    $this->actingAs($this->user);
});

it ('Create city with missing parameter', function () {
    $response = $this->postJson(route('api.storeCity'), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['country_id', 'name', 'state_id']);
});

it ('Create city with invalid country_id', function () {
    $payload = [
        'country_id' => 9999,
        'name' => 'California',
    ];

    $response = $this->postJson(route('api.storeCity'), $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['country_id']);
});

it ('Create city successfully', function () {
    // create country first
    $countryPayload = [
        'name' => 'United States',
        'iso3' => 'USA',
        'iso2' => 'US',
        'phone_code' => '+1',
        'currency' => 'USD',
    ];

    $countryResponse = $this->postJson(route('api.storeCountry'), $countryPayload);
    $countryResponse->assertStatus(201);

    $country = \Modules\Company\Models\Country::where('iso3', 'USA')->first();
    $countryId = $country->id;

    $state = \Modules\Company\Models\State::factory()->create([
        'country_id' => $countryId,
        'name' => 'California',
    ]);

    // create state
    $cityPayload = [
        'country_id' => $countryId,
        'state_id' => $state->id,
        'name' => 'Los Angeles',
    ];

    $cityResponse = $this->postJson(route('api.storeCity'), $cityPayload);

    $cityResponse->assertStatus(201);
    $cityResponse->assertJsonPath('message', __('notification.successCreateCity'));
});

it ('Create city with duplicate name in the same country', function () {
    $country = \Modules\Company\Models\Country::factory()->create([
        'name' => 'United States',
        'iso3' => 'USA',
        'iso2' => 'US',
        'phone_code' => '+1',
        'currency' => 'USD',
    ]);
    $state = \Modules\Company\Models\State::factory()->create([
        'country_id' => $country->id,
        'name' => 'California',
    ]);
    $countryId = $country->id;

    $city = \Modules\Company\Models\City::factory()->create([
        'country_id' => $countryId,
        'state_id' => $state->id,
        'name' => 'Los Angeles',
    ]);

    // create state
    $cityPayload = [
        'country_id' => $countryId,
        'state_id' => $state->id,
        'name' => $city->name,
    ];

    $cityResponse = $this->postJson(route('api.storeCity'), $cityPayload);

    $cityResponse->assertStatus(422);
    $cityResponse->assertJsonValidationErrors(['name']);
});

it ('Update city successfully', function () {
    $country = \Modules\Company\Models\Country::factory()->create([
        'name' => 'United States',
        'iso3' => 'USA',
        'iso2' => 'US',
        'phone_code' => '+1',
        'currency' => 'USD',
    ]);
    $state = \Modules\Company\Models\State::factory()->create([
        'country_id' => $country->id,
        'name' => 'California',
    ]);

    $city = \Modules\Company\Models\City::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'name' => 'Los Angeles',
        'country_code' => $country->iso3,
    ]);

    $updatePayload = [
        'name' => 'New Los Angeles',
        'country_id' => $country->id,
        'state_id' => $state->id,
    ];

    $updateResponse = $this->putJson(route('api.updateCity', ['cityId' => $city->id]), $updatePayload);

    $updateResponse->assertStatus(201);
    $updateResponse->assertJsonPath('message', __('notification.successUpdateCity'));
});

it ('Update city with missing parameters', function () {
    $country = \Modules\Company\Models\Country::factory()->create([
        'name' => 'United States',
        'iso3' => 'USA',
        'iso2' => 'US',
        'phone_code' => '+1',
        'currency' => 'USD',
    ]);
    $state = \Modules\Company\Models\State::factory()->create([
        'country_id' => $country->id,
        'name' => 'California',
    ]);
    $city = \Modules\Company\Models\City::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'name' => 'Los Angeles',
        'country_code' => $country->iso3,
    ]);

    $updatePayload = [];

    $updateResponse = $this->putJson(route('api.updateCity', ['cityId' => $city->id]), $updatePayload);

    $updateResponse->assertStatus(422);
    $updateResponse->assertJsonValidationErrors(['name']);
});

it ('Delete city successfully', function () {
    $country = \Modules\Company\Models\Country::factory()->create([
        'name' => 'United States',
        'iso3' => 'USA',
        'iso2' => 'US',
        'phone_code' => '+1',
        'currency' => 'USD',
    ]);
    $state = \Modules\Company\Models\State::factory()->create([
        'country_id' => $country->id,
        'name' => 'California',
    ]);
    $city = \Modules\Company\Models\City::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'name' => 'Los Angeles',
        'country_code' => $country->iso3,
    ]);

    $deleteResponse = $this->deleteJson(route('api.deleteCity', ['cityId' => $city->id]));

    $deleteResponse->assertStatus(201);
    $deleteResponse->assertJsonPath('message', __('notification.successDeleteCity'));
});

it ('Delete city when have relation to project deals', function () {
    $country = \Modules\Company\Models\Country::factory()->create([
        'name' => 'United States',
        'iso3' => 'USA',
        'iso2' => 'US',
        'phone_code' => '+1',
        'currency' => 'USD',
    ]);
    $state = \Modules\Company\Models\State::factory()->create([
        'country_id' => $country->id,
        'name' => 'California',
    ]);
    $city = \Modules\Company\Models\City::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'name' => 'Los Angeles',
        'country_code' => $country->iso3,
    ]);

    // create project deal relation
    $projectDeal = \Modules\Production\Models\ProjectDeal::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
    ]);

    $deleteResponse = $this->deleteJson(route('api.deleteCity', ['cityId' => $city->id]));

    $deleteResponse->assertStatus(400);
    $deleteResponse->assertJsonPath('message', __('notification.cannotDeleteCityBcsRelation'));
});

it ('Delete city when city not found', function () {
    $deleteResponse = $this->deleteJson(route('api.deleteCity', ['cityId' => 9999]));

    $deleteResponse->assertStatus(400);
    $deleteResponse->assertJsonPath('message', __('notification.dataNotFound'));
});