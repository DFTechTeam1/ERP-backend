<?php

beforeEach(function () {
    $this->user = initAuthenticateUser(permissions: [
        'create_country'
    ]);

    $this->actingAs($this->user);
});

it ('Create country with missing parameters', function () {
    $response = $this->postJson(route('api.storeCountry'), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'iso3', 'iso2', 'phone_code', 'currency']);
});

it ('Create country successfully', function () {
    $payload = [
        'name' => 'Test Country',
        'iso3' => 'TST',
        'iso2' => 'TS',
        'phone_code' => '123',
        'currency' => 'TSTC',
    ];

    $response = $this->postJson(route('api.storeCountry'), $payload);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'message' => __('notification.successCreateCountry'),
    ]);

    $this->assertDatabaseHas('countries', [
        'name' => 'Test Country',
        'iso3' => 'TST',
        'iso2' => 'TS',
        'phone_code' => '123',
        'currency' => 'TSTC',
    ]);
});

it ('Create country with duplicate name', function () {
    // First, create a country
    \Modules\Company\Models\Country::factory()->create([
        'name' => 'Existing Country',
        'iso3' => 'EXC',
        'iso2' => 'EC',
        'phone_code' => '456',
        'currency' => 'EXCC',
    ]);

    // Attempt to create another country with the same name
    $payload = [
        'name' => 'Existing Country',
        'iso3' => 'NEW',
        'iso2' => 'NC',
        'phone_code' => '789',
        'currency' => 'NEWC',
    ];

    $response = $this->postJson(route('api.storeCountry'), $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});
