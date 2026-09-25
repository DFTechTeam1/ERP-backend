<?php

use App\Enums\Finance\RefundStatus;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Services\ProjectDealService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/**
 * ProjectDealService::storeRefund() and its HTTP route.
 *
 * storeRefund records at most ONE refund request per project deal: it decrypts the deal uid,
 * bails with `eventHasBeenAlreadyHaveRefund` if a refund already exists, otherwise stores a
 * Pending refund. created_by is stamped from the authenticated user by the model's creating hook.
 *
 * The route POST /api/production/project/deals/{projectDealUid}/refund sits behind auth.session
 * and PermissionCheck:create_refund, and validates the body with the CreateRefund form request.
 */
function refundService(): ProjectDealService
{
    return app(ProjectDealService::class);
}

/**
 * A valid create-refund payload; override any field as needed.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function refundPayload(array $overrides = []): array
{
    return array_merge([
        'refund_type' => 'fixed',
        'refund_amount' => 250000,
        'refund_percentage' => 0,
        'refund_reason' => 'Client cancelled the event',
    ], $overrides);
}

/**
 * Crypt::encryptString is base64, which can contain '/', and a '/' in a URL path segment breaks
 * route matching. Re-encrypt (the IV is random each call) until we get a '/'-free uid so the e2e
 * route resolves to a single {projectDealUid}.
 */
function refundDealUid(ProjectDeal $deal): string
{
    do {
        $uid = Crypt::encryptString((string) $deal->id);
    } while (str_contains($uid, '/'));

    return $uid;
}

function createRefundPermission(): Permission
{
    return Permission::firstOrCreate(['name' => 'create_refund', 'guard_name' => 'sanctum']);
}

/** A fresh sanctum-authenticated user holding the create_refund permission. */
function actAsRefundCreator(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(createRefundPermission());
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user, 'sanctum');

    return $user;
}

// ---- Service: storeRefund -------------------------------------------------

describe('storeRefund (service)', function () {
    it('creates a pending refund for the deal and stamps the actor', function () {
        $user = User::factory()->create();
        actingAs($user);
        $deal = ProjectDeal::factory()->create();

        $response = refundService()->storeRefund(
            refundPayload(['refund_amount' => 250000]),
            Crypt::encryptString((string) $deal->id),
        );

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('notification.successCreateProjectDealRefundRequest'));

        assertDatabaseHas('project_deal_refunds', [
            'project_deal_id' => $deal->id,
            'refund_amount' => 250000,
            'refund_type' => 'fixed',
            'status' => RefundStatus::Pending->value,
            'created_by' => $user->id,
        ]);
    });

    it('rejects a second refund for a deal that already has one', function () {
        $user = User::factory()->create();
        actingAs($user);
        $deal = ProjectDeal::factory()->create();
        $uid = Crypt::encryptString((string) $deal->id);

        expect(refundService()->storeRefund(refundPayload(), $uid)['error'])->toBeFalse();

        $second = refundService()->storeRefund(refundPayload(['refund_amount' => 999999]), $uid);

        expect($second['error'])->toBeTrue()
            ->and($second['message'])->toBe(__('notification.eventHasBeenAlreadyHaveRefund'));

        // the duplicate must not have been persisted
        assertDatabaseCount('project_deal_refunds', 1);
    });

    it('stores the percentage and reason for a percentage refund', function () {
        $user = User::factory()->create();
        actingAs($user);
        $deal = ProjectDeal::factory()->create();

        refundService()->storeRefund(
            refundPayload([
                'refund_type' => 'percentage',
                'refund_percentage' => 30,
                'refund_reason' => 'Partial refund agreed with client',
            ]),
            Crypt::encryptString((string) $deal->id),
        );

        assertDatabaseHas('project_deal_refunds', [
            'project_deal_id' => $deal->id,
            'refund_type' => 'percentage',
            'refund_percentage' => 30,
            'refund_reason' => 'Partial refund agreed with client',
            'status' => RefundStatus::Pending->value,
        ]);
    });
});

// ---- E2E: POST /api/production/project/deals/{uid}/refund ------------------

describe('POST project/deals/{uid}/refund (e2e)', function () {
    it('stores a refund for a permitted user and returns success', function () {
        $user = actAsRefundCreator();
        $deal = ProjectDeal::factory()->create();

        $this->postJson(
            '/api/production/project/deals/'.refundDealUid($deal).'/refund',
            refundPayload(['refund_amount' => 500000]),
        )
            ->assertStatus(201)
            ->assertJson(['message' => __('notification.successCreateProjectDealRefundRequest')]);

        assertDatabaseHas('project_deal_refunds', [
            'project_deal_id' => $deal->id,
            'refund_amount' => 500000,
            'status' => RefundStatus::Pending->value,
            'created_by' => $user->id,
        ]);
    });

    it('blocks a user without the create_refund permission', function () {
        createRefundPermission();
        actingAs(User::factory()->create(), 'sanctum');
        $deal = ProjectDeal::factory()->create();

        // PermissionCheck returns an error envelope (BadRequest), not a 403.
        $this->postJson(
            '/api/production/project/deals/'.refundDealUid($deal).'/refund',
            refundPayload(),
        )
            ->assertStatus(400)
            ->assertJson(['message' => "You don't have permission to access this resource."]);

        assertDatabaseCount('project_deal_refunds', 0);
    });

    it('rejects an invalid body (refund_type is required)', function () {
        actAsRefundCreator();
        $deal = ProjectDeal::factory()->create();

        $this->postJson(
            '/api/production/project/deals/'.refundDealUid($deal).'/refund',
            ['refund_amount' => 100000], // missing refund_type
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['refund_type']);

        assertDatabaseCount('project_deal_refunds', 0);
    });
});
