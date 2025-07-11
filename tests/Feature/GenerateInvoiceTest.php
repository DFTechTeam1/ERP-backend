<?php

use App\Enums\Production\ProjectDealStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Modules\Company\Models\City;
use Modules\Company\Models\Country;
use Modules\Company\Models\State;
use Modules\Finance\Jobs\InvoiceHasBeenCreatedJob;
use Modules\Finance\Models\Transaction;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectQuotation;

it("GenerateBillInvoiceWhenHaveUnpaidInvoice", function () {
    $country = Country::factory()
        ->has(
            State::factory()
                ->has(City::factory())
        )
        ->create();
        
    $projectDeal = ProjectDeal::factory()
        ->has(ProjectQuotation::factory()->state([
            'is_final' => 1
        ]), 'quotations')
        ->has(\Modules\Finance\Models\Invoice::factory()
            ->state([
                'is_main' => 0,
                'status' => \App\Enums\Transaction\InvoiceStatus::Unpaid->value
            ]))
        ->create([
            'status' => ProjectDealStatus::Final->value,
            'country_id' => $country->id,
            'state_id' => $country->states[0]->id,
            'city_id' => $country->states[0]->cities[0]->id
        ]);

    $service = setInvoiceService();

    $response = $service->store(
        data: [
            'transaction_date' => now()->format('Y-m-d'),
            'amount' => 10000000
        ],
        projectDealUid: \Illuminate\Support\Facades\Crypt::encryptString($projectDeal->id)
    );

    expect($response['error'])->toBeTrue();
    expect($response['message'])->toContain(__('notification.cannotCreateInvoiceIfYouHaveAnotherUnpaidInovice'));
});

it('GenerateBillInvoice', function () {
    Bus::fake();

    $country = Country::factory()
        ->has(
            State::factory()
                ->has(City::factory())
        )
        ->create();
        
    $projectDeal = ProjectDeal::factory()
        ->has(ProjectQuotation::factory()->state([
            'is_final' => 1
        ]), 'quotations')
        ->has(\Modules\Finance\Models\Invoice::factory())
        ->create([
            'status' => ProjectDealStatus::Final->value,
            'country_id' => $country->id,
            'state_id' => $country->states[0]->id,
            'city_id' => $country->states[0]->cities[0]->id
        ]);

    $service = setInvoiceService();

    $response = $service->store(
        data: [
            'transaction_date' => now()->format('Y-m-d'),
            'amount' => 10000000
        ],
        projectDealUid: \Illuminate\Support\Facades\Crypt::encryptString($projectDeal->id)
    );

    expect($response['error'])->toBeFalse();
    expect($response)->toHaveKeys(['error', 'message', 'data.url']);

    // get invoice data to check the content
    $invoiceData = \Modules\Finance\Models\Invoice::selectRaw('raw_data')
        ->where('project_deal_id', $projectDeal->id)
        ->where('is_main', 0)
        ->where('sequence', 1)
        ->first();

    expect($invoiceData)->toBeObject();
    expect($invoiceData->raw_data)->toBeArray();
    expect(count($invoiceData->raw_data['transactions']))->toBeGreaterThan(0);

    $this->assertDatabaseCount('invoices', 2);
    $this->assertDatabaseHas('invoices', [
        'is_main' => 0,
        'project_deal_id' => $projectDeal->id,
        'sequence' => 1
    ]);

    Bus::assertDispatched(InvoiceHasBeenCreatedJob::class);
});