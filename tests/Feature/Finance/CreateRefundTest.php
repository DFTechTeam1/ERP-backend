<?php

use App\Enums\Finance\RefundStatus;
use Illuminate\Support\Facades\Crypt;
use Modules\Production\Models\ProjectDeal;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it ('Create refund with missing parameters', function () {
    $projectDeal = ProjectDeal::factory()->withQuotation(1000000)->create();
    $projectDealUid = Crypt::encryptString($projectDeal->id);
    $response = $this->postJson(route('api.production.project-deal.refund', $projectDealUid), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['refund_type', 'refund_amount']);
});

it ('Create refund successfully with fixed amount', function () {
    $projectDeal = ProjectDeal::factory()->withQuotation(1000000)->create();
    $projectDealUid = Crypt::encryptString($projectDeal->id);
    $payload = [
        'refund_type' => 'fixed',
        'refund_amount' => 500000,
        'refund_reason' => 'Customer request',
    ];
    $response = $this->postJson(route('api.production.project-deal.refund', $projectDealUid), $payload);

    $response->assertStatus(201);

    $this->assertDatabaseCount('project_deal_refunds', 1);
    $this->assertDatabaseHas('project_deal_refunds', [
        'project_deal_id' => $projectDeal->id,
        'refund_amount' => 500000,
        'refund_percentage' => 0,
        'refund_reason' => 'Customer request',
        'status' => RefundStatus::Pending->value, // Pending
        'refund_type' => 'fixed',
    ]);
});

it ('Create refund successfully with percentage amount', function () {
    $projectDeal = ProjectDeal::factory()->withQuotation(2000000)->create();
    $projectDealUid = Crypt::encryptString($projectDeal->id);
    $payload = [
        'refund_type' => 'percentage',
        'refund_amount' => 400000,
        'refund_percentage' => 20,
        'refund_reason' => 'Product defect',
    ];
    $response = $this->postJson(route('api.production.project-deal.refund', $projectDealUid), $payload);

    $response->assertStatus(201);

    $this->assertDatabaseCount('project_deal_refunds', 1);
    $this->assertDatabaseHas('project_deal_refunds', [
        'project_deal_id' => $projectDeal->id,
        'refund_amount' => 400000,
        'refund_percentage' => 20,
        'refund_reason' => 'Product defect',    
        'status' => RefundStatus::Pending->value, // Pending
        'refund_type' => 'percentage',
    ]);
});