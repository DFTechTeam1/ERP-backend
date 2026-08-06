<?php

/**
 * Regression: loading `->with('sourceable')` on a transaction imported by
 * `finance:sync-recap` (sourceable_type = 'recap_import') MUST NOT throw
 * "Class 'recap_import' not found". The morph alias is registered in
 * FinanceServiceProvider::registerMorphAliases.
 */

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Models\Transaction;
use Modules\Production\Models\ProjectDeal;

it('resolves the "recap_import" morph alias to a real class', function () {
    expect(Relation::getMorphedModel('recap_import'))->toBe(Transaction::class);
});

it('does not throw when eager-loading sourceable on a recap-imported transaction', function () {
    $deal = ProjectDeal::factory()->create();
    $user = User::query()->first() ?? User::factory()->create();

    // Insert a bare-minimum transaction that mimics what finance:sync-recap writes.
    // Using DB::table bypasses the observer + Auth::id() default in the Transaction model.
    $id = DB::table('transactions')->insertGetId([
        'uid' => (string) Str::uuid(),
        'project_deal_id' => $deal->id,
        'customer_id' => $deal->customer_id,
        'payment_amount' => 1_000_000,
        'reference' => 'test',
        'note' => 'test recap import',
        'trx_id' => 'TRX - test',
        'transaction_date' => now(),
        'transaction_type' => 'down_payment',
        'created_by' => $user->id,
        'invoice_id' => null,
        'sourceable_type' => 'recap_import',
        'sourceable_id' => 0,
        'debit_credit' => 'debit',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Eager-load — this is what the fetch endpoint does; used to throw.
    $trx = Transaction::query()->with('sourceable')->find($id);

    expect($trx)->not->toBeNull()
        ->and($trx->sourceable_type)->toBe('recap_import')
        ->and($trx->sourceable)->toBeNull(); // id 0 → nothing found → null, not an error
});
