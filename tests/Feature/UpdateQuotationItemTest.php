<?php

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Update quotation item with unique name', function () {
    $quotation = \Modules\Production\Models\QuotationItem::factory()->count(2)->create();

    $response = $this->putJson('/api/production/quotations/'.$quotation[1]->id, [
        'name' => $quotation[0]->name,
    ]);

    $response->assertStatus(422);
    expect($response->json())->toHaveKey('errors');
    expect($response->json()['errors'])->toHaveKey('name');
    expect($response->json()['errors']['name'][0])->toBe('The name has already been taken.');
});

it('Update quotation return success', function () {
    $payload = [
        'name' => 'Name',
    ];

    $quotation = \Modules\Production\Models\QuotationItem::factory()->create();

    $service = createQuotationItemService();

    $response = $service->update(data: $payload, id: $quotation->id);

    expect($response)->toHaveKey('error');
    expect($response['error'])->toBeFalse();

    $this->assertDatabaseHas('quotation_items', [
        'name' => $payload['name'],
        'id' => $quotation->id,
    ]);
});
