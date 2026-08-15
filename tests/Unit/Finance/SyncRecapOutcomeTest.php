<?php

/**
 * Post-commit verdict. The operator must never have to read the stats table
 * to work out whether the recap actually landed in the database.
 */

use Modules\Finance\Console\SyncRecapTransactionsCommand as Cmd;

function recapStats(array $overrides = []): array
{
    return array_merge([
        'matched' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'skipped_cancel' => 0,
        'inserted_dp' => 0, 'inserted_pel' => 0, 'skipped_existing' => 0,
        'fully_paid_flipped' => 0, 'invoices_marked_paid' => 0, 'refunds_inserted' => 0,
    ], $overrides);
}

describe('applied run', function () {
    it('reports SYNCED when anything was written', function (string $key) {
        $outcome = Cmd::syncOutcome(recapStats(['matched' => 1, $key => 1]), dryRun: false);

        expect($outcome['status'])->toBe(Cmd::OUTCOME_SYNCED)
            ->and($outcome['headline'])->toContain('DATA SYNCED')
            ->and($outcome['headline'])->toContain('1 change(s) committed');
    })->with([
        'dp insert' => 'inserted_dp',
        'pelunasan insert' => 'inserted_pel',
        'refund insert' => 'refunds_inserted',
        'fully paid flip' => 'fully_paid_flipped',
        'invoice marked paid' => 'invoices_marked_paid',
    ]);

    it('sums every kind of write into the committed count', function () {
        $outcome = Cmd::syncOutcome(recapStats([
            'matched' => 10,
            'inserted_dp' => 4,
            'inserted_pel' => 3,
            'refunds_inserted' => 1,
            'fully_paid_flipped' => 2,
            'invoices_marked_paid' => 5,
        ]), dryRun: false);

        expect($outcome['headline'])->toContain('15 change(s) committed')
            ->and($outcome['details'][0])
            ->toContain('4 DP + 3 pelunasan')
            ->toContain('refunds: 1')
            ->toContain('invoices marked paid: 5')
            ->toContain('deals flipped to fully paid: 2');
    });

    it('reports NOT SYNCED when matched rows were all already up to date', function () {
        $outcome = Cmd::syncOutcome(recapStats([
            'matched' => 7,
            'skipped_existing' => 12,
        ]), dryRun: false);

        expect($outcome['status'])->toBe(Cmd::OUTCOME_NOT_SYNCED)
            ->and($outcome['headline'])->toContain('NOT SYNCED')
            ->and($outcome['details'])->toContain('All 7 matched row(s) were already up to date.');
    });

    it('reports NOT SYNCED when nothing matched at all', function () {
        $outcome = Cmd::syncOutcome(recapStats(['unmatched' => 40]), dryRun: false);

        expect($outcome['status'])->toBe(Cmd::OUTCOME_NOT_SYNCED)
            ->and($outcome['details'])->toContain('No Excel row matched a project deal.');
    });
});

describe('dry run', function () {
    it('never claims data was synced, even when there is plenty to write', function () {
        $outcome = Cmd::syncOutcome(recapStats([
            'matched' => 5,
            'inserted_dp' => 5,
            'inserted_pel' => 5,
        ]), dryRun: true);

        expect($outcome['status'])->toBe(Cmd::OUTCOME_DRY_RUN)
            ->and($outcome['headline'])
            ->toContain('DRY RUN')
            ->toContain('NOT SYNCED')
            ->toContain('rolled back')
            ->and($outcome['details'][0])->toContain('10 change(s) would be applied')
            ->and($outcome['details'][1])->toContain('Re-run without --dry-run');
    });

    it('says the database already matches when there is nothing to write', function () {
        $outcome = Cmd::syncOutcome(recapStats(['matched' => 3, 'skipped_existing' => 6]), dryRun: true);

        expect($outcome['status'])->toBe(Cmd::OUTCOME_DRY_RUN)
            ->and($outcome['details'][0])->toContain('No change would be applied')
            ->and($outcome['details'])->not->toContain('Re-run without --dry-run to sync for real.');
    });
});

describe('follow-up notes', function () {
    it('surfaces rows that still need attention', function () {
        $outcome = Cmd::syncOutcome(recapStats([
            'matched' => 2,
            'inserted_dp' => 2,
            'unmatched' => 9,
            'ambiguous' => 4,
        ]), dryRun: false);

        expect(implode("\n", $outcome['details']))
            ->toContain('13 row(s) need attention: 9 unmatched, 4 ambiguous');
    });

    it('mentions skipped existing payments and cancelled rows', function () {
        $outcome = Cmd::syncOutcome(recapStats([
            'matched' => 2,
            'inserted_dp' => 1,
            'skipped_existing' => 3,
            'skipped_cancel' => 8,
        ]), dryRun: false);

        $details = implode("\n", $outcome['details']);
        expect($details)
            ->toContain('3 payment(s) skipped')
            ->toContain('8 row(s) skipped because their status is Cancel.');
    });

    it('stays quiet about follow-ups when there are none', function () {
        $outcome = Cmd::syncOutcome(recapStats(['matched' => 1, 'inserted_dp' => 1]), dryRun: false);

        expect($outcome['details'])->toHaveCount(1);
    });

    it('tolerates a partial stats array without warnings', function () {
        $outcome = Cmd::syncOutcome(['inserted_dp' => 2], dryRun: false);

        expect($outcome['status'])->toBe(Cmd::OUTCOME_SYNCED)
            ->and($outcome['headline'])->toContain('2 change(s) committed');
    });
});
