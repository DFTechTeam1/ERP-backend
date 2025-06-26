<?php

use App\Models\User;
use App\Services\GeneralService;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Repository\TransactionRepository;
use Ramsey\Uuid\Uuid;

it('Inovice number when do not have any data', function () {
    $service = new GeneralService();

    $transaction = Mockery::mock(TransactionRepository::class);
    $transaction->shouldReceive('list')
        ->withAnyArgs()
        ->andReturn(collect([]));

    $roman = $service->monthToRoman(month: (int) now()->format('m'));
    $year = now()->format('Y');
    $response = $service->generateInvoiceNumber();

    expect($response)->toBe("{$roman}/{$year} - 0951");
});

it('Invoice number when we have 100 data', function () {
    $service = new GeneralService();

    $roman = $service->monthToRoman(month: (int) now()->format('m'));
    $year = now()->format('Y');

    $user = User::factory()->create();

    Transaction::withoutEvents(function () use($roman, $year, $user) {
        Transaction::factory()
            ->create([
                'trx_id' => "{$roman}/{$year} - 0980",
                'uid' => Uuid::uuid4(),
                'created_by' => $user->id
            ]);
    });

    $response = $service->generateInvoiceNumber();

    expect($response)->toBe("{$roman}/{$year} - 0981");
});
