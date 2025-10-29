<?php

beforeEach(function () {
    $this->user = initAuthenticateUser(permissions: [
        'create_country'
    ]);

    $this->actingAs($this->user);
});

it ('Update country with missing parameters', function () {
    // First, create a country to update
    $country = \Modules\Company\Models\Country::factory()->create([
        'name' => 'Old Country',
        'iso3' => 'OLD',
        'iso2' => 'OC',
        'phone_code' => '111',
        'currency' => 'OLDC',
    ]);

    // Attempt to update the country with missing parameters
    $response = $this->putJson(route('api.updateCountry', ['countryId' => $country->id]), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'iso3', 'iso2', 'phone_code', 'currency']);
});

it ('Update country with duplicate iso3', function () {
    // First, create two countries
    $existingCountry = \Modules\Company\Models\Country::factory()->create([
        'name' => 'Existing Country',
        'iso3' => 'EXC',
        'iso2' => 'EC',
        'phone_code' => '222',
        'currency' => 'EXCC',
    ]);

    $countryToUpdate = \Modules\Company\Models\Country::factory()->create([
        'name' => 'Country To Update',
        'iso3' => 'CTU',
        'iso2' => 'CU',
        'phone_code' => '333',
        'currency' => 'CTUC',
    ]);

    // Attempt to update the second country with a duplicate iso3
    $payload = [
        'name' => 'Updated Country',
        'iso3' => 'EXC', // Duplicate iso3
        'iso2' => 'UC',
        'phone_code' => '444',
        'currency' => 'UPDC',
    ];

    $response = $this->putJson(route('api.updateCountry', ['countryId' => $countryToUpdate->id]), $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['iso3']);
});

it ('Update country successfully', function () {
    // First, create a country to update
    $country = \Modules\Company\Models\Country::factory()->create([
        'name' => 'Old Country',
        'iso3' => 'OLD',
        'iso2' => 'OC',
        'phone_code' => '111',
        'currency' => 'OLDC',
    ]);

    // Prepare the update payload
    $payload = [
        'name' => 'Updated Country',
        'iso3' => 'UPD',
        'iso2' => 'UC',
        'phone_code' => '555',
        'currency' => 'UPDC',
    ];

    // Attempt to update the country
    $response = $this->putJson(route('api.updateCountry', ['countryId' => $country->id]), $payload);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'message' => __('notification.successUpdateCountry'),
    ]);

    // Verify the database has the updated country
    $this->assertDatabaseHas('countries', [
        'id' => $country->id,
        'name' => 'Updated Country',
        'iso3' => 'UPD',
        'iso2' => 'UC',
        'phone_code' => '555',
        'currency' => 'UPDC',
    ]);
});