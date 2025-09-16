<?php

use Modules\Production\Models\QuotationItem;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Create quotation item with unique name', function () {
    $name = 'Unique Item Name';
    $quotation = QuotationItem::factory()->create([
        'name' => $name,
    ]);

    $response = $this->postJson('/api/production/quotations', [
        'name' => $name,
    ]);

    $response->assertStatus(422);
    expect($response->json())->toHaveKey('errors');
    expect($response->json()['errors'])->toHaveKey('name');
    expect($response->json()['errors']['name'][0])->toBe('The name has already been taken.');
});

it('Create quotation item return success', function () {
    $payload = [
        'name' => 'Item',
    ];

    $service = createQuotationItemService();

    $response = $service->store($payload);

    expect($response)->toHaveKey('error');
    expect($response['error'])->toBeFalse();

    $this->assertDatabaseHas('quotation_items', [
        'name' => $payload['name'],
    ]);
});
