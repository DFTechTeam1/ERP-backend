<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealMarketing;
use Modules\Production\Services\ProjectDealService;

use function Pest\Laravel\actingAs;

/**
 * registerOnLead() re-registers an existing project deal as a lead on the Express
 * production service: it loads the deal, builds a lead payload from it, and POSTs
 * that payload - carrying the caller's bearer token - to
 * `{express_endpoint}/production/project-leads`.
 *
 * The tests fake the HTTP layer (Http::fake) so nothing leaves the process, and
 * pin the express endpoint to a known host so the target URL is assertable. The
 * deal is refreshed after creation so its attributes carry the same DB-native
 * types the service reads back, keeping strict comparisons stable.
 */
function registerLeadService(): ProjectDealService
{
    return app(ProjectDealService::class);
}

beforeEach(function () {
    config(['app.express_endpoint' => 'https://express.test']);

    $employee = Employee::factory()->withUser()->create();
    $this->actor = User::where('employee_id', $employee->id)->firstOrFail();
    actingAs($this->actor);
});

describe('registerOnLead', function () {
    it('posts the deal to the express project-leads endpoint and returns success', function () {
        Http::fake();

        $deal = ProjectDeal::factory()->create()->refresh();
        $phone = $deal->customer->phone;
        $actorEmployeeId = $this->actor->employee_id;

        $response = registerLeadService()->registerOnLead(Crypt::encryptString((string) $deal->id));

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe('Success create leads')
            ->and($response['code'])->toBe(201);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($deal, $phone, $actorEmployeeId) {
            return $request->url() === 'https://express.test/production/project-leads'
                && $request->method() === 'POST'
                && $request['name'] === $deal->name
                && $request['customerPhone'] === $phone
                && $request['projectDate'] === $deal->project_date
                && $request['eventType'] === $deal->event_type->value
                && $request['venue'] === $deal->venue
                && $request['cityId'] === $deal->city_id
                && $request['collaboration'] === $deal->collaboration
                && $request['note'] === $deal->note
                && $request['projectClassId'] === $deal->project_class_id
                && $request['is_final'] === 1
                && $request['projectDealId'] === $deal->id
                && $request['createdBy'] === $actorEmployeeId;
        });
    });

    it('uses the first marketing employee as createdBy when the deal has marketing staff', function () {
        Http::fake();

        $deal = ProjectDeal::factory()->create();
        $marketingEmployee = Employee::factory()->create();
        ProjectDealMarketing::factory()->create([
            'project_deal_id' => $deal->id,
            'employee_id' => $marketingEmployee->id,
        ]);

        $marketingEmployeeId = $marketingEmployee->id;
        $actorEmployeeId = $this->actor->employee_id;

        registerLeadService()->registerOnLead(Crypt::encryptString((string) $deal->id));

        Http::assertSent(fn (Request $request) => $request['createdBy'] === $marketingEmployeeId
            && $request['createdBy'] !== $actorEmployeeId);
    });

    it('falls back to the authenticated user employee id as createdBy when there is no marketing', function () {
        Http::fake();

        $deal = ProjectDeal::factory()->create();
        $actorEmployeeId = $this->actor->employee_id;

        registerLeadService()->registerOnLead(Crypt::encryptString((string) $deal->id));

        Http::assertSent(fn (Request $request) => $request['createdBy'] === $actorEmployeeId);
    });

    it('forwards the caller bearer token to the express request', function () {
        Http::fake();

        request()->headers->set('Authorization', 'Bearer caller-token-xyz');
        $deal = ProjectDeal::factory()->create();

        registerLeadService()->registerOnLead(Crypt::encryptString((string) $deal->id));

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer caller-token-xyz'));
    });

    it('forwards the deal led_area and led_detail to the express payload', function () {
        Http::fake();

        $deal = ProjectDeal::factory()->create()->refresh();

        registerLeadService()->registerOnLead(Crypt::encryptString((string) $deal->id));

        Http::assertSent(function (Request $request) use ($deal) {
            return $request['totalLed'] !== null
                && (float) $request['totalLed'] === (float) $deal->led_area
                && $request['ledDetail'] !== null
                // ledDetail is forwarded to Express as a JSON string, not a raw array
                && $request['ledDetail'] === json_encode($deal->led_detail);
        });
    });

    it('returns an error and sends nothing when the project deal does not exist', function () {
        Http::fake();

        $response = registerLeadService()->registerOnLead(Crypt::encryptString('999999'));

        expect($response['error'])->toBeTrue()
            ->and($response['code'])->toBe(400)
            ->and($response['message'])->toContain('Project deal is not found');

        Http::assertNothingSent();
    });

    it('returns an error response when the express request fails', function () {
        Http::fake(fn () => throw new ConnectionException('Express is unreachable'));

        $deal = ProjectDeal::factory()->create();

        $response = registerLeadService()->registerOnLead(Crypt::encryptString((string) $deal->id));

        expect($response['error'])->toBeTrue()
            ->and($response['code'])->toBe(400);
    });

    it('returns an error and sends nothing when the uid cannot be decrypted', function () {
        Http::fake();

        $response = registerLeadService()->registerOnLead('not-a-valid-encrypted-string');

        expect($response['error'])->toBeTrue()
            ->and($response['code'])->toBe(400);

        Http::assertNothingSent();
    });
});
