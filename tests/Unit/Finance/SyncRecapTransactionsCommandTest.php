<?php

use App\Enums\Finance\RefundStatus;
use Modules\Finance\Console\SyncRecapTransactionsCommand;

describe('refundPercentage', function () {
    it('computes integer percent of fee', function (float $refund, float $fee, int $expected) {
        expect(SyncRecapTransactionsCommand::refundPercentage($refund, $fee))->toBe($expected);
    })->with([
        'exact 10%' => [1_000_000, 10_000_000, 10],
        'exact 25%' => [2_500_000, 10_000_000, 25],
        'rounds up' => [1_666_666, 10_000_000, 17],
        'rounds down' => [1_444_444, 10_000_000, 14],
        'zero refund' => [0, 10_000_000, 0],
        'zero fee' => [5_000_000, 0, 0],
        'both zero' => [0, 0, 0],
        'refund larger than fee' => [15_000_000, 10_000_000, 150],
    ]);

    it('never returns null', function () {
        // Regression: NOT NULL column default(0) was being overridden by null.
        expect(SyncRecapTransactionsCommand::refundPercentage(0, 0))->toBeInt();
    });
});

describe('refundStatusFromKeterangan', function () {
    it('maps "Sudah Refund" variants to Paid', function (string $keterangan) {
        expect(SyncRecapTransactionsCommand::refundStatusFromKeterangan($keterangan))
            ->toBe(RefundStatus::Paid->value);
    })->with([
        'Sudah Refund',
        'sudah refund',
        'SUDAH REFUND',
        '  Sudah Refund  ',
        'sudah refund via transfer',
    ]);

    it('maps everything else to Pending', function (string $keterangan) {
        expect(SyncRecapTransactionsCommand::refundStatusFromKeterangan($keterangan))
            ->toBe(RefundStatus::Pending->value);
    })->with([
        'Belum Refund',
        'Nett',
        '',
        'refund pending',
        'akan diproses',
    ]);
});

describe('normalizeName', function () {
    it('lowercases and strips punctuation', function () {
        [$normalized, $tokens] = SyncRecapTransactionsCommand::normalizeName('Daniel & Jessica');
        expect($normalized)->toBe('daniel jessica')
            ->and($tokens)->toBe(['daniel', 'jessica']);
    });

    it('drops noise words like "The", "Wedding", "Of"', function () {
        [, $tokens] = SyncRecapTransactionsCommand::normalizeName('The Wedding of Luna Maxime');
        expect($tokens)->toBe(['luna', 'maxime']);
    });

    it('drops short (<3 char) tokens', function () {
        [, $tokens] = SyncRecapTransactionsCommand::normalizeName('AB Cd Efg');
        expect($tokens)->toBe(['efg']);
    });

    it('handles the "Leony Sweet 17th Birthday" case that motivated fuzzy matching', function () {
        [, $excel] = SyncRecapTransactionsCommand::normalizeName('Leony Sweet 17th');
        [, $db] = SyncRecapTransactionsCommand::normalizeName('Leony Sweet 17th Birthday');

        // "sweet" and "birthday" are noise; "leony" and "17th" survive.
        // The two names reduce to the same token set, so a fuzzy matcher scores 1.0.
        expect($excel)->toBe(['17th', 'leony'])
            ->and($db)->toBe(['17th', 'leony']);
    });

    it('returns tokens sorted (order-independent match)', function () {
        [, $a] = SyncRecapTransactionsCommand::normalizeName('Alpha Bravo');
        [, $b] = SyncRecapTransactionsCommand::normalizeName('Bravo Alpha');
        expect($a)->toBe($b);
    });

    it('returns an empty token array when all words are noise', function () {
        [, $tokens] = SyncRecapTransactionsCommand::normalizeName('The Wedding Of');
        expect($tokens)->toBe([]);
    });
});
