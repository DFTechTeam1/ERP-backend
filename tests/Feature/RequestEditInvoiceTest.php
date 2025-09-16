<?php

use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Enums\Transaction\InvoiceStatus;
use App\Services\GeneralService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Jobs\ApproveInvoiceChangesJob;
use Modules\Finance\Jobs\RequestInvoiceChangeJob;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceRequestUpdate;
use Modules\Hrd\Models\Employee;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertEquals;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Request invoice test with no changes in it', function () {
    Bus::fake();

    $invoice = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::Unpaid->value,
        ]);

    $response = postJson(
        uri: route('api.invoices.updateTemporaryData', ['projectDealUid' => $invoice->project_deal_id]),
        data: [
            'amount' => $invoice->amount,
            'payment_date' => date('Y-m-d', strtotime($invoice->payment_date)),
            'invoice_uid' => $invoice->uid,
        ]
    );

    $response->assertStatus(422);
    assertArrayHasKey('errors', $response->json());
    assertArrayHasKey('amount', $response->json()['errors']);
    assertEquals('No changes are submitted', $response->json()['errors']['amount'][0]);

    Bus::assertNotDispatched(RequestInvoiceChangeJob::class);
});

it('Request invoice test with changes in amount', function () {
    Bus::fake();

    $invoice = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::Unpaid->value,
            'amount' => 15000000,
        ]);
    $response = postJson(
        uri: route('api.invoices.updateTemporaryData', ['projectDealUid' => $invoice->project_deal_id]),
        data: [
            'amount' => 17000000,
            'invoice_uid' => $invoice->uid,
        ]
    );

    $response->assertStatus(201);

    $this->assertDatabaseHas('invoice_request_updates', [
        'amount' => '17000000.00',
        'payment_date' => null,
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
        'request_by' => auth()->id(),
    ]);

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => InvoiceStatus::WaitingChangesApproval->value,
    ]);

    Bus::assertDispatched(RequestInvoiceChangeJob::class);
});

it('Request invoice test with changes in payment date', function () {
    Bus::fake();

    $changesDate = now()->addDays(3)->format('Y-m-d');
    $invoice = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::Unpaid->value,
            'amount' => 15000000,
        ]);
    $response = postJson(
        uri: route('api.invoices.updateTemporaryData', ['projectDealUid' => $invoice->project_deal_id]),
        data: [
            'payment_date' => $changesDate,
            'invoice_uid' => $invoice->uid,
        ]
    );

    $response->assertStatus(201);

    $this->assertDatabaseHas('invoice_request_updates', [
        'amount' => null,
        'payment_date' => $changesDate,
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
        'request_by' => auth()->id(),
    ]);

    Bus::assertDispatched(RequestInvoiceChangeJob::class);
});

it('Get data for request edit notification', function () {
    // create director
    Employee::factory()
        ->withUser()
        ->create([
            'email' => 'wesleywiyadi@gmail.com',
        ]);

    $invoiceRequest = InvoiceRequestUpdate::factory()->create();

    $response = (new GeneralService)->getDataForRequestInvoiceChangeNotification(invoiceRequestId: $invoiceRequest->id);

    expect($response)->toHaveKeys(['actor', 'invoice', 'director', 'changes']);
});

it('Approval change from email', function () {
    Bus::fake();

    $invoice = Invoice::factory()->create([
        'raw_data' => [
            'fixPrice' => 'Rp50,0000,000',
            'remainingPayment' => 'Rp30,0000,000',
            'transactions' => [
                [
                    'id' => null,
                    'payment' => 'Rp20,000,000',
                    'transaction_date' => '23 July 2025',
                ],
            ],
            'paymentDue' => '23 July 2025',
            'trxDate' => '19 July 2025',
        ],
    ]);

    $invoiceRequest = InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
    ]);

    $director = Employee::factory()
        ->withUser()
        ->create([
            'email' => 'wesleywiyadi@gmail.com',
        ]);
    $employee = Employee::with('user:id,uid,employee_id')
        ->find($director->id);

    // generate url
    $response = (new GeneralService)->getDataForRequestInvoiceChangeNotification(invoiceRequestId: $invoiceRequest->id);

    expect($response)->toHaveKeys(['actor', 'invoice', 'director', 'changes']);

    $data = $response['invoice'];
    $approvalUrl = URL::signedRoute(
        name: 'api.invoices.approveChanges',
        parameters: [
            'invoiceUid' => $data->invoice->uid,
            'dir' => $employee->user->uid,
            'cid' => $data->id,
        ],
        expiration: now()->addHours(5)
    );
    $trueResponse = getJson($approvalUrl);

    $this->assertDatabaseHas('invoice_request_updates', [
        'id' => $invoiceRequest->id,
        'status' => InvoiceRequestUpdateStatus::Approved->value,
        'approved_by' => $employee->user->id,
    ]);

    Bus::assertDispatched(ApproveInvoiceChangesJob::class);
});
