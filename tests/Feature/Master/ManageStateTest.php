<?php

beforeEach(function () {
    $this->user = initAuthenticateUser(permissions: [
        'create_country'
    ]);

    $this->actingAs($this->user);
});

it ('Create state with missing parameter', function () {
    $response = $this->postJson(route('api.storeState'), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['country_id', 'name']);
});

it ('Create state with invalid country_id', function () {
    $payload = [
        'country_id' => 9999,
        'name' => 'California',
    ];

    $response = $this->postJson(route('api.storeState'), $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['country_id']);
});

it ('Create state successfully', function () {
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

    // create state
    $statePayload = [
        'country_id' => $countryId,
        'name' => 'California',
    ];

    $stateResponse = $this->postJson(route('api.storeState'), $statePayload);

    $stateResponse->assertStatus(201);
    $stateResponse->assertJsonPath('message', __('notification.successCreateState'));
});

it ('Create state with duplicate name in the same country', function () {
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

    // create state
    $statePayload = [
        'country_id' => $countryId,
        'name' => $state->name,
    ];

    $stateResponse = $this->postJson(route('api.storeState'), $statePayload);

    $stateResponse->assertStatus(422);
    $stateResponse->assertJsonValidationErrors(['name']);
});

it ('Update state successfully', function () {
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

    $updatePayload = [
        'name' => 'New California',
        'country_id' => $country->id,
    ];

    $updateResponse = $this->putJson(route('api.updateState', ['stateId' => $state->id]), $updatePayload);

    $updateResponse->assertStatus(201);
    $updateResponse->assertJsonPath('message', __('notification.successUpdateState'));
});

it ('Update state with missing parameters', function () {
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

    $updatePayload = [];

    $updateResponse = $this->putJson(route('api.updateState', ['stateId' => $state->id]), $updatePayload);

    $updateResponse->assertStatus(422);
    $updateResponse->assertJsonValidationErrors(['name']);
});

it ('Delete state successfully', function () {
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

    $deleteResponse = $this->deleteJson(route('api.deleteState', ['stateId' => $state->id]));

    $deleteResponse->assertStatus(201);
    $deleteResponse->assertJsonPath('message', __('notification.successDeleteState'));
});

it ('Delete state when have relation to project deals', function () {
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

    // create project deal relation
    $projectDeal = \Modules\Production\Models\ProjectDeal::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
    ]);

    $deleteResponse = $this->deleteJson(route('api.deleteState', ['stateId' => $state->id]));

    $deleteResponse->assertStatus(400);
    $deleteResponse->assertJsonPath('message', __('notification.cannotDeleteStateBcsRelation'));
});

it ('Delete state when state not found', function () {
    $deleteResponse = $this->deleteJson(route('api.deleteState', ['stateId' => 9999]));

    $deleteResponse->assertStatus(400);
    $deleteResponse->assertJsonPath('message', __('notification.dataNotFound'));
});
