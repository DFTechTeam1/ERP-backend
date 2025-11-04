<?php

beforeEach(function () {
    $this->user = initAuthenticateUser(permissions: [
        'delete_country'
    ]);

    $this->actingAs($this->user);
});

it ('Delete country successfully', function () {
    // First, create a country to delete
    $country = \Modules\Company\Models\Country::factory()->create([
        'name' => 'Country To Delete',
        'iso3' => 'CTD',
        'iso2' => 'CD',
        'phone_code' => '555',
        'currency' => 'CTDC',
    ]);

    // Attempt to delete the country
    $response = $this->deleteJson(route('api.deleteCountry', ['countryId' => $country->id]));

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'message' => __('notification.successDeleteCountry'),
    ]);

    $this->assertDatabaseMissing('countries', [
        'id' => $country->id,
    ]);
});

it ('Delete country when have relation with project deals', function () {
    $country = \Modules\Company\Models\Country::factory()->create([
        'name' => 'Country With Relation',
        'iso3' => 'CWR',
        'iso2' => 'CR',
        'phone_code' => '777',
        'currency' => 'CWRC',
    ]);

    $state = \Modules\Company\Models\State::factory()->create([
        'country_id' => $country->id,
        'name' => 'State With Relation',
        'country_code' => $country->iso3,
    ]);

    \Modules\Production\Models\ProjectDeal::factory()->create([
        'state_id' => $state->id,
        'country_id' => $country->id,
        'name' => 'Project Deal 1',
    ]);

    // Attempt to delete the country
    $response = $this->deleteJson(route('api.deleteCountry', ['countryId' => $country->id]));
    $response->assertStatus(400);
    $response->assertJsonFragment([
        'message' => __('notification.cannotDeleteCountryBcsRelation'),
    ]);
});


