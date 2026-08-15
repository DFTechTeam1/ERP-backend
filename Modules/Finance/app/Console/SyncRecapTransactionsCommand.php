<?php

namespace Modules\Finance\Console;

use App\Enums\Finance\RefundStatus;
use App\Enums\Transaction\InvoiceStatus;
use App\Enums\Transaction\TransactionType;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Production\Models\ProjectDeal;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SyncRecapTransactionsCommand extends Command
{
    protected $signature = 'finance:sync-recap
        {path? : Absolute path to the .xlsx file (defaults to storage/app/recap.xlsx)}
        {--year=* : Sheet names to import (defaults to 2025 2026 2027)}
        {--min-score=0.5 : Minimum token-overlap score for fuzzy matching}
        {--date-tolerance=3 : Max +/- days between Excel Tanggal and project_date}
        {--as-user= : users.id to attribute inserts to (default: 1 / first admin). Refunds require a real user.}
        {--dry-run : Print what would happen, insert nothing}
        {--auto-apply : Apply Tier-1 (exact) + Tier-2 (single-winner fuzzy) matches. Otherwise ask for each fuzzy match.}';

    protected $description = 'Sync DP and Pelunasan payments from RECAP EVENT DFACTORY.xlsx into transactions.';

    private const SOURCEABLE_TYPE = 'recap_import';

    public const OUTCOME_SYNCED = 'synced';

    public const OUTCOME_NOT_SYNCED = 'not_synced';

    public const OUTCOME_DRY_RUN = 'dry_run';

    private int $syncUserId = 0;

    /** @var array<string> */
    private const NOISE_TOKENS = [
        'the', 'wedding', 'of', 'and', 'holy', 'matrimony', 'sweet', 'birthday',
        'engagement', 'ceremony', 'anniversary', 'party', 'event', 'mr', 'mrs',
        'ms', 'family', 'th', 'st', 'nd', 'rd',
    ];

    public function handle(): int
    {
        // Resolve the "as user" for created_by columns BEFORE anything else.
        // Refunds have a NOT NULL, FK-constrained created_by, so a bad id
        // would blow up mid-loop and roll back every prior insert; fail fast.
        $requestedUser = $this->option('as-user');
        $this->syncUserId = $this->resolveSyncUser($requestedUser !== null ? (int) $requestedUser : null);
        if ($this->syncUserId <= 0) {
            $this->error(
                $requestedUser !== null
                    ? "--as-user={$requestedUser} does not exist in users table"
                    : 'Could not resolve a default user for created_by. Pass --as-user=<id> (e.g. --as-user=1).'
            );

            return self::FAILURE;
        }
        $this->info("Attributing inserts to users.id = {$this->syncUserId}");

        $path = $this->argument('path') ?: storage_path('app/recap.xlsx');
        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");
            $this->newLine();
            $this->line('The Laravel container mounts storage as a named volume, so the recap file');
            $this->line('needs to be copied into the container before running this command.');
            $this->newLine();
            $this->line('From the host, run:');
            $this->line('  docker cp "/var/www/apps/dfactory/erp_workspace/RECAP EVENT DFACTORY.xlsx" \\');
            $this->line('           erp_dev-laravel-1:/var/www/html/storage/app/recap.xlsx');
            $this->newLine();
            $this->line('Or, from erp_infra/, run the convenience target:');
            $this->line('  make recap-sync-dry   (or: make recap-sync to apply)');

            return self::FAILURE;
        }

        $years = $this->option('year') ?: ['2025', '2026', '2027'];
        $minScore = (float) $this->option('min-score');
        $dateTolerance = (int) $this->option('date-tolerance');
        $dryRun = (bool) $this->option('dry-run');
        $autoApply = (bool) $this->option('auto-apply');

        $validation = $this->validateRecapFile($path, $years);
        if ($validation !== null) {
            $this->error($validation);

            return self::FAILURE;
        }

        $this->info("Reading {$path}");
        $rows = $this->readExcel($path, $years);
        $this->info('Excel rows to consider: '.count($rows));

        $deals = $this->loadDeals($years);
        $this->info('DB project_deals in range: '.$deals->count());

        $dealIdx = $this->indexDeals($deals);

        $stats = [
            'matched' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'skipped_cancel' => 0,
            'inserted_dp' => 0, 'inserted_pel' => 0, 'skipped_existing' => 0,
            'fully_paid_flipped' => 0, 'invoices_marked_paid' => 0, 'refunds_inserted' => 0,
        ];
        $matchedLog = [];
        $unmatchedLog = [];
        $ambiguousLog = [];
        $overpaidLog = [];

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $bar->advance();

                if (strcasecmp($row['status'], 'Cancel') === 0) {
                    $stats['skipped_cancel']++;

                    continue;
                }

                $match = $this->matchDeal($row, $dealIdx, $minScore, $dateTolerance);

                if ($match['kind'] === 'none') {
                    $stats['unmatched']++;
                    $unmatchedLog[] = $row;

                    continue;
                }

                if ($match['kind'] === 'ambiguous') {
                    $stats['ambiguous']++;
                    $ambiguousLog[] = [$row, $match['candidates']];

                    continue;
                }

                if ($match['kind'] === 'fuzzy' && ! $autoApply) {
                    $confirmed = $this->confirmFuzzy($row, $match['deal']);
                    if (! $confirmed) {
                        $stats['ambiguous']++;
                        $ambiguousLog[] = [$row, [$match['deal']]];

                        continue;
                    }
                }

                $stats['matched']++;
                $result = $this->syncTransactionsForDeal($row, $match['deal'], $dryRun);
                $stats['inserted_dp'] += $result['dp_inserted'] ? 1 : 0;
                $stats['inserted_pel'] += $result['pel_inserted'] ? 1 : 0;
                $stats['skipped_existing'] += $result['skipped_existing'];
                $stats['fully_paid_flipped'] += $result['fully_paid_flipped'] ? 1 : 0;
                $stats['invoices_marked_paid'] += $result['invoices_marked_paid'];
                $stats['refunds_inserted'] += $result['refund_inserted'] ? 1 : 0;
                $matchedLog[] = [$row, $match['deal'], $match['kind'], $result];
                if ($result['overpaid'] > 0) {
                    $overpaidLog[] = [$row, $match['deal'], $result['overpaid']];
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine(2);
            $this->error('Aborted: '.$e->getMessage());
            $this->error('NOT SYNCED — the database transaction was rolled back, no rows were written.');

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        $this->writeComparisonReport($stats, $matchedLog, $unmatchedLog, $ambiguousLog, $overpaidLog, $dryRun);

        $this->reportSyncOutcome($stats, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Print the post-commit verdict. The stats table above says what happened
     * to each row; this says the one thing the operator actually needs —
     * whether the data is now in the database or not.
     *
     * @param  array<string, int>  $stats
     */
    private function reportSyncOutcome(array $stats, bool $dryRun): void
    {
        $outcome = self::syncOutcome($stats, $dryRun);

        $this->newLine();
        if ($outcome['status'] === self::OUTCOME_SYNCED) {
            $this->info($outcome['headline']);
        } else {
            $this->warn($outcome['headline']);
        }
        foreach ($outcome['details'] as $detail) {
            $this->line('  '.$detail);
        }
    }

    /**
     * Turn the run counters into a plain-language verdict.
     *
     * @param  array<string, int>  $stats
     * @return array{status: string, headline: string, details: array<int, string>}
     */
    public static function syncOutcome(array $stats, bool $dryRun): array
    {
        $count = fn (string $key): int => (int) ($stats[$key] ?? 0);

        $writes = $count('inserted_dp')
            + $count('inserted_pel')
            + $count('refunds_inserted')
            + $count('fully_paid_flipped')
            + $count('invoices_marked_paid');

        $breakdown = sprintf(
            'transactions: %d DP + %d pelunasan | refunds: %d | invoices marked paid: %d | deals flipped to fully paid: %d',
            $count('inserted_dp'),
            $count('inserted_pel'),
            $count('refunds_inserted'),
            $count('invoices_marked_paid'),
            $count('fully_paid_flipped')
        );

        if ($dryRun) {
            $status = self::OUTCOME_DRY_RUN;
            $headline = 'DRY RUN — NOT SYNCED. The database transaction was rolled back, no rows were written.';
            $details = $writes > 0
                ? [
                    "{$writes} change(s) would be applied — {$breakdown}",
                    'Re-run without --dry-run to sync for real.',
                ]
                : ['No change would be applied — the database already matches the recap.'];
        } elseif ($writes > 0) {
            $status = self::OUTCOME_SYNCED;
            $headline = "DATA SYNCED — {$writes} change(s) committed to the database.";
            $details = [$breakdown];
        } else {
            $status = self::OUTCOME_NOT_SYNCED;
            $headline = 'NOT SYNCED — the run committed, but there was nothing to write.';
            $details = [
                $count('matched') > 0
                    ? "All {$count('matched')} matched row(s) were already up to date."
                    : 'No Excel row matched a project deal.',
            ];
        }

        if ($count('skipped_existing') > 0) {
            $details[] = "{$count('skipped_existing')} payment(s) skipped — a transaction of that type already exists on the deal.";
        }

        $needsAttention = $count('unmatched') + $count('ambiguous');
        if ($needsAttention > 0) {
            $details[] = sprintf(
                '%d row(s) need attention: %d unmatched, %d ambiguous — see the Unmatched / Ambiguous sheets in the comparison report.',
                $needsAttention,
                $count('unmatched'),
                $count('ambiguous')
            );
        }

        if ($count('skipped_cancel') > 0) {
            $details[] = "{$count('skipped_cancel')} row(s) skipped because their status is Cancel.";
        }

        return ['status' => $status, 'headline' => $headline, 'details' => $details];
    }

    /**
     * @param  array<string>  $years
     * @return array<int, array<string, mixed>>
     */
    private function readExcel(string $path, array $years): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $out = [];
        foreach ($years as $sheetName) {
            if (! $spreadsheet->sheetNameExists($sheetName)) {
                $this->warn("Sheet not found: {$sheetName}");

                continue;
            }
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $columns = $this->findColumns($sheet);
            if (! $columns['client']) {
                $this->warn("No 'Client' header in sheet {$sheetName}, skipping");

                continue;
            }
            $highestRow = $sheet->getHighestDataRow();
            for ($r = 3; $r <= $highestRow; $r++) {
                $client = trim((string) $sheet->getCell($columns['client'].$r)->getValue());
                if ($client === '') {
                    continue;
                }
                $out[] = [
                    'sheet' => $sheetName,
                    'row' => $r,
                    'client' => $client,
                    'status' => $this->cellString($sheet, $columns['status'], $r),
                    'date' => $this->cellDate($sheet, $columns['tanggal'], $r),
                    'dp' => $this->cellFloat($sheet, $columns['dp'], $r),
                    'pel' => $this->cellFloat($sheet, $columns['pelunasan'], $r),
                    'fee' => $this->cellFloat($sheet, $columns['fee'], $r),
                    'tgl_lunas' => $this->cellDate($sheet, $columns['tgl_lunas'], $r),
                    'refund' => $this->cellFloat($sheet, $columns['refund'], $r),
                    'tgl_refund' => $this->cellDate($sheet, $columns['tgl_refund'], $r),
                    'keterangan' => $this->cellString($sheet, $columns['keterangan'], $r),
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, ?string>
     */
    private function findColumns(Worksheet $sheet): array
    {
        $map = [
            'client' => null, 'tanggal' => null, 'dp' => null, 'pelunasan' => null,
            'fee' => null, 'status' => null, 'tgl_lunas' => null,
            'refund' => null, 'tgl_refund' => null, 'keterangan' => null,
        ];
        for ($col = 'A'; $col !== 'Z'; $col++) {
            $name = strtolower(trim((string) $sheet->getCell($col.'2')->getValue()));
            if ($name === '') {
                continue;
            }
            if (str_starts_with($name, 'client')) {
                $map['client'] = $col;
            } elseif ($name === 'tanggal') {
                $map['tanggal'] = $col;
            } elseif (str_starts_with($name, 'pembayaran dp')) {
                $map['dp'] = $col;
            } elseif ($name === 'pelunasan') {
                $map['pelunasan'] = $col;
            } elseif ($name === 'fee') {
                $map['fee'] = $col;
            } elseif ($name === 'status') {
                $map['status'] = $col;
            } elseif (str_starts_with($name, 'tgl lunas')) {
                $map['tgl_lunas'] = $col;
            } elseif (str_starts_with($name, 'tanggal refund')) {
                $map['tgl_refund'] = $col;
            } elseif ($name === 'refund') {
                $map['refund'] = $col;
            } elseif ($name === 'keterangan') {
                $map['keterangan'] = $col;
            }
        }

        return $map;
    }

    private function cellString(Worksheet $sheet, ?string $col, int $row): string
    {
        return $col ? trim((string) $sheet->getCell($col.$row)->getValue()) : '';
    }

    private function cellFloat(Worksheet $sheet, ?string $col, int $row): float
    {
        return $col ? (float) $sheet->getCell($col.$row)->getValue() : 0.0;
    }

    private function cellDate(Worksheet $sheet, ?string $col, int $row): ?string
    {
        if (! $col) {
            return null;
        }
        $value = $sheet->getCell($col.$row)->getValue();
        if (is_numeric($value) && $value > 20000) {
            return gmdate('Y-m-d', (int) (((float) $value - 25569) * 86400));
        }
        if (is_string($value) && $value !== '') {
            $ts = strtotime(str_replace('/', '-', $value));

            return $ts ? date('Y-m-d', $ts) : null;
        }

        return null;
    }

    /**
     * @param  array<string>  $years
     * @return Collection<int, ProjectDeal>
     */
    private function loadDeals(array $years): Collection
    {
        return ProjectDeal::query()
            ->whereNull('deleted_at')
            ->whereIn(DB::raw('YEAR(project_date)'), $years)
            ->with([
                'transactions:id,project_deal_id,payment_amount,transaction_type,transaction_date,sourceable_type,sourceable_id',
                'finalQuotation:id,project_deal_id,fix_price,is_final',
                'invoices:id,project_deal_id,amount,paid_amount,status,is_main,is_down_payment',
                'refund:id,project_deal_id,refund_amount,status',
            ])
            ->get(['id', 'identifier_number', 'name', 'project_date', 'is_fully_paid', 'customer_id']);
    }

    /**
     * @return array<int, array{deal: ProjectDeal, norm: string, tokens: array<int, string>}>
     */
    private function indexDeals(Collection $deals): array
    {
        $idx = [];
        foreach ($deals as $deal) {
            [$norm, $tokens] = self::normalizeName($deal->name);
            $idx[] = ['deal' => $deal, 'norm' => $norm, 'tokens' => $tokens];
        }

        return $idx;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array{deal: ProjectDeal, norm: string, tokens: array<int, string>}>  $dealIdx
     * @return array{kind: string, deal?: ProjectDeal, candidates?: array<int, ProjectDeal>}
     */
    private function matchDeal(array $row, array $dealIdx, float $minScore, int $dateTolerance): array
    {
        [$xnorm, $xtokens] = self::normalizeName($row['client']);

        $exact = [];
        foreach ($dealIdx as $entry) {
            if ($entry['norm'] === $xnorm) {
                $exact[] = $entry['deal'];
            }
        }
        if (count($exact) === 1) {
            return ['kind' => 'exact', 'deal' => $exact[0]];
        }

        if (empty($xtokens)) {
            return count($exact) > 1
                ? ['kind' => 'ambiguous', 'candidates' => $exact]
                : ['kind' => 'none'];
        }

        $candidates = [];
        foreach ($dealIdx as $entry) {
            if (empty($entry['tokens'])) {
                continue;
            }
            if ($entry['deal']->project_date
                && $row['sheet']
                && substr((string) $entry['deal']->project_date, 0, 4) !== $row['sheet']
            ) {
                continue;
            }
            $inter = array_intersect($xtokens, $entry['tokens']);
            if (! $inter) {
                continue;
            }
            $score = count($inter) / min(count($xtokens), count($entry['tokens']));
            if ($row['date'] && $entry['deal']->project_date) {
                $diff = abs(strtotime($row['date']) - strtotime((string) $entry['deal']->project_date)) / 86400;
                if ($diff > $dateTolerance) {
                    continue;
                }
            }
            if ($score >= $minScore) {
                $candidates[] = [$score, $entry['deal']];
            }
        }

        usort($candidates, fn ($a, $b) => $b[0] <=> $a[0]);

        if (empty($candidates)) {
            return ['kind' => 'none'];
        }
        if (count($candidates) === 1 || ($candidates[0][0] - $candidates[1][0]) > 0.2) {
            return ['kind' => 'fuzzy', 'deal' => $candidates[0][1]];
        }

        return [
            'kind' => 'ambiguous',
            'candidates' => array_map(fn ($c) => $c[1], $candidates),
        ];
    }

    private function confirmFuzzy(array $row, ProjectDeal $deal): bool
    {
        $this->newLine();
        $this->line("Excel  : [{$row['sheet']}] '{$row['client']}' ({$row['date']}) DP=".number_format($row['dp']).' Pel='.number_format($row['pel']));
        $this->line("Deal   : [{$deal->identifier_number}] '{$deal->name}' ({$deal->project_date})");

        return $this->confirm('Match?', true);
    }

    /**
     * @return array{
     *     dp_inserted: bool, pel_inserted: bool, skipped_existing: int, overpaid: float,
     *     fully_paid_flipped: bool, invoices_marked_paid: int, refund_inserted: bool
     * }
     */
    private function syncTransactionsForDeal(array $row, ProjectDeal $deal, bool $dryRun): array
    {
        $existing = $deal->transactions;
        $dpExisting = $existing->firstWhere('transaction_type', TransactionType::DownPayment);
        $pelExisting = $existing->firstWhere('transaction_type', TransactionType::Repayment);
        $existingTotal = (float) $existing->sum('payment_amount');

        $result = [
            'dp_inserted' => false,
            'pel_inserted' => false,
            'skipped_existing' => 0,
            'overpaid' => 0.0,
            'fully_paid_flipped' => false,
            'invoices_marked_paid' => 0,
            'refund_inserted' => false,
        ];

        if ($row['dp'] > 0) {
            if ($dpExisting) {
                $result['skipped_existing']++;
            } else {
                $trxId = $this->insertTransaction($deal, $row, TransactionType::DownPayment, (float) $row['dp'], $dryRun);
                $result['invoices_marked_paid'] += $this->markSubInvoicePaid($deal, isDownPayment: true, transactionId: $trxId, payment: (float) $row['dp'], dryRun: $dryRun);
                $result['dp_inserted'] = true;
            }
        }

        if ($row['pel'] > 0) {
            if ($pelExisting) {
                $result['skipped_existing']++;
            } else {
                $trxId = $this->insertTransaction($deal, $row, TransactionType::Repayment, (float) $row['pel'], $dryRun);
                $result['invoices_marked_paid'] += $this->markSubInvoicePaid($deal, isDownPayment: false, transactionId: $trxId, payment: (float) $row['pel'], dryRun: $dryRun);
                $result['pel_inserted'] = true;
            }
        }

        $newTotal = $existingTotal
            + ($result['dp_inserted'] ? (float) $row['dp'] : 0)
            + ($result['pel_inserted'] ? (float) $row['pel'] : 0);

        $finalPrice = optional($deal->finalQuotation)->fix_price !== null
            ? (float) $deal->finalQuotation->fix_price
            : 0.0;

        // Rp 1 tolerance guards against float rounding.
        $shouldBeFullyPaid = $finalPrice > 0 && $newTotal >= ($finalPrice - 1);

        if ($shouldBeFullyPaid && ! $deal->is_fully_paid) {
            // Count invoices that will flip (accurate for both dry-run and real).
            $stillUnpaid = $deal->invoices->where('status', InvoiceStatus::Unpaid)->count();
            $result['invoices_marked_paid'] += $stillUnpaid;

            if (! $dryRun) {
                DB::table('project_deals')->where('id', $deal->id)->update([
                    'is_fully_paid' => 1,
                    'updated_at' => now(),
                ]);
                DB::table('invoices')
                    ->where('project_deal_id', $deal->id)
                    ->where('status', InvoiceStatus::Unpaid->value)
                    ->update([
                        'status' => InvoiceStatus::Paid->value,
                        'paid_amount' => DB::raw('amount'),
                        'updated_at' => now(),
                    ]);
            }
            $result['fully_paid_flipped'] = true;
        }

        if ($row['refund'] > 0 && ! $deal->refund) {
            if (! $dryRun) {
                DB::table('project_deal_refunds')->insert([
                    'project_deal_id' => $deal->id,
                    'refund_amount' => (float) $row['refund'],
                    'refund_percentage' => $this->refundPercentage((float) $row['refund'], (float) $row['fee']),
                    'refund_type' => 'fixed',
                    'refund_reason' => 'Imported from RECAP EVENT DFACTORY.xlsx'.
                        ($row['tgl_refund'] ? " (Tanggal Refund: {$row['tgl_refund']})" : ''),
                    'status' => $this->refundStatusFromKeterangan((string) $row['keterangan']),
                    'created_by' => $this->syncUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $result['refund_inserted'] = true;
        }

        $result['overpaid'] = max(0.0, $existingTotal - ($row['dp'] + $row['pel']));

        return $result;
    }

    /**
     * Structural check the uploaded xlsx before we touch DB or spend time parsing 500 rows.
     * Returns a human-readable error message, or null when the file is usable.
     *
     * Checks in order:
     *   1. file exists and is readable
     *   2. extension is .xlsx (guards against .xls / .csv / random uploads)
     *   3. PhpSpreadsheet can open it (guards against corrupted / renamed files)
     *   4. at least one of the requested year sheets is present
     *   5. every present year sheet has the columns we depend on
     *
     * @param  array<string>  $years
     */
    public function validateRecapFile(string $path, array $years): ?string
    {
        if (! is_readable($path)) {
            return "File not readable: {$path}. Upload the recap xlsx to storage/app/recap.xlsx first (or use `make recap-sync-dry`).";
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            return "Expected a .xlsx file, got: {$path}. The command reads via PhpSpreadsheet Xlsx reader only — convert .xls / .csv first.";
        }

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $e) {
            return "Could not open {$path} as xlsx: {$e->getMessage()}. Re-upload; the file may be corrupted or a different format.";
        }

        $availableSheets = $spreadsheet->getSheetNames();
        $matchedYears = array_values(array_intersect($years, $availableSheets));

        if (empty($matchedYears)) {
            return sprintf(
                "None of the requested year sheets exist in %s.\n  Requested: %s\n  Available: %s",
                $path,
                implode(', ', $years),
                implode(', ', $availableSheets) ?: '(none)'
            );
        }

        $requiredHeaders = ['client', 'tanggal', 'pembayaran dp', 'pelunasan'];
        $missingByYear = [];
        foreach ($matchedYears as $year) {
            $sheet = $spreadsheet->getSheetByName($year);
            $headers = [];
            for ($col = 'A'; $col !== 'Z'; $col++) {
                $val = strtolower(trim((string) $sheet->getCell($col.'2')->getValue()));
                if ($val !== '') {
                    $headers[] = $val;
                }
            }

            $missing = array_filter($requiredHeaders, function (string $needle) use ($headers) {
                foreach ($headers as $h) {
                    if (str_starts_with($h, $needle)) {
                        return false;
                    }
                }

                return true;
            });

            if (! empty($missing)) {
                $missingByYear[$year] = array_values($missing);
            }
        }

        if (! empty($missingByYear)) {
            $lines = ["Header row 2 is missing required columns in one or more sheets of {$path}:"];
            foreach ($missingByYear as $year => $missing) {
                $lines[] = "  [{$year}] missing: ".implode(', ', $missing);
            }
            $lines[] = 'Required (case-insensitive, prefix match): '.implode(', ', $requiredHeaders);

            return implode("\n", $lines);
        }

        $this->info(sprintf(
            'Validated %s — sheets found: %s',
            basename($path),
            implode(', ', $matchedYears)
        ));

        return null;
    }

    /**
     * Resolve a valid users.id for created_by attribution. Fails to 0 if nothing suitable.
     * Preference: explicit --as-user > id 1 > lowest existing id.
     */
    private function resolveSyncUser(?int $requestedId): int
    {
        if ($requestedId !== null) {
            return DB::table('users')->where('id', $requestedId)->exists() ? $requestedId : 0;
        }
        if (DB::table('users')->where('id', 1)->exists()) {
            return 1;
        }

        return (int) DB::table('users')->orderBy('id')->value('id');
    }

    public static function refundPercentage(float $refund, float $fee): int
    {
        if ($fee <= 0 || $refund <= 0) {
            return 0;
        }

        return (int) round(($refund / $fee) * 100);
    }

    public static function refundStatusFromKeterangan(string $keterangan): string
    {
        return str_starts_with(strtolower(trim($keterangan)), 'sudah')
            ? RefundStatus::Paid->value
            : RefundStatus::Pending->value;
    }

    /**
     * Normalize a client/deal name for matching.
     *
     * @return array{0: string, 1: array<int, string>} [$normalized, $meaningfulTokens]
     */
    public static function normalizeName(string $s): array
    {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        $tokens = array_values(array_filter(
            explode(' ', $s),
            fn (string $t) => strlen($t) >= 3 && ! in_array($t, self::NOISE_TOKENS, true)
        ));
        sort($tokens);

        return [$s, $tokens];
    }

    /**
     * Bypasses the Transaction observer (which expects an invoice_id) and returns
     * the inserted id so the caller can link it to a sub-invoice.
     */
    private function insertTransaction(ProjectDeal $deal, array $row, TransactionType $type, float $amount, bool $dryRun): int
    {
        $date = $type === TransactionType::Repayment && $row['tgl_lunas']
            ? $row['tgl_lunas']
            : ($row['date'] ?? date('Y-m-d'));

        $payload = [
            'uid' => (string) Str::uuid(),
            'project_deal_id' => $deal->id,
            'customer_id' => $deal->customer_id,
            'payment_amount' => $amount,
            'reference' => "RECAP {$row['sheet']} row {$row['row']}",
            'note' => "Imported from RECAP EVENT DFACTORY.xlsx ({$type->label()})",
            'trx_id' => 'TRX - '.$deal->identifier_number.' - '.substr((string) $date, 0, 4),
            'transaction_date' => $date,
            'transaction_type' => $type->value,
            'created_by' => $this->syncUserId,
            'invoice_id' => null,
            'sourceable_type' => self::SOURCEABLE_TYPE,
            'sourceable_id' => 0,
            'debit_credit' => 'debit',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($dryRun) {
            return 0;
        }

        return (int) DB::table('transactions')->insertGetId($payload);
    }

    /**
     * Mark the deal's matching sub-invoice Paid and link the transaction to it.
     * Skips if no unpaid sub-invoice of the requested type exists.
     */
    private function markSubInvoicePaid(ProjectDeal $deal, bool $isDownPayment, int $transactionId, float $payment, bool $dryRun): int
    {
        $invoice = $deal->invoices
            ->where('is_main', 0)
            ->where('is_down_payment', $isDownPayment ? 1 : 0)
            ->where('status', InvoiceStatus::Unpaid)
            ->first();

        if (! $invoice) {
            return 0;
        }

        if ($dryRun) {
            return 1;
        }

        DB::table('invoices')->where('id', $invoice->id)->update([
            'status' => InvoiceStatus::Paid->value,
            'paid_amount' => $payment,
            'updated_at' => now(),
        ]);

        if ($transactionId > 0) {
            DB::table('transactions')->where('id', $transactionId)->update(['invoice_id' => $invoice->id]);
        }

        return 1;
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<int, array{0: array<string,mixed>, 1: ProjectDeal, 2: string, 3: array<string,mixed>}>  $matched
     * @param  array<int, array<string, mixed>>  $unmatched
     * @param  array<int, array{0: array<string,mixed>, 1: array<int, ProjectDeal>}>  $ambiguous
     * @param  array<int, array{0: array<string,mixed>, 1: ProjectDeal, 2: float}>  $overpaid
     */
    private function writeComparisonReport(array $stats, array $matched, array $unmatched, array $ambiguous, array $overpaid, bool $dryRun): void
    {
        $dir = storage_path('app/recap_sync');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = "{$dir}/comparison.xlsx";

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->buildSummarySheet($spreadsheet, $stats, $dryRun);
        $this->buildMatchedSheet($spreadsheet, $matched);
        $this->buildAmbiguousSheet($spreadsheet, $ambiguous);
        $this->buildUnmatchedSheet($spreadsheet, $unmatched);
        $this->buildOverpaidSheet($spreadsheet, $overpaid);

        $spreadsheet->setActiveSheetIndex(0);
        (new Xlsx($spreadsheet))->save($path);

        $this->info("Comparison report written to {$path}");
    }

    private const FMT_MONEY = '_-"Rp "* #,##0_-;[Red]-"Rp "* #,##0_-;_-"Rp "* "-"_-;_-@_-';

    private const FMT_DATE = 'yyyy-mm-dd';

    private const FMT_INT = '#,##0';

    private const COLOR_HEADER = 'E5E7EB';

    private const COLOR_ZEBRA = 'F8FAFC';

    /**
     * @param  array<string, int>  $stats
     */
    private function buildSummarySheet(Spreadsheet $book, array $stats, bool $dryRun): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Summary');

        $sheet->fromArray([
            ['RECAP EVENT DFACTORY — sync comparison'],
            ['Generated at', now()->toDateTimeString()],
            ['Mode', $dryRun ? 'DRY RUN (no rows written)' : 'APPLIED'],
            [],
            ['Metric', 'Count'],
        ]);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getStyle('A5:B5')->getFont()->setBold(true);
        $sheet->getStyle('A5:B5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_HEADER);
        $sheet->getStyle('A5:B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 6;
        foreach ($stats as $key => $value) {
            $sheet->setCellValue("A{$row}", $key);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }
        $lastRow = $row - 1;
        $sheet->getStyle("B6:B{$lastRow}")->getNumberFormat()->setFormatCode(self::FMT_INT);
        $sheet->getStyle("B6:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->applyBorders($sheet, "A5:B{$lastRow}");

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(18);
    }

    /**
     * @param  array<int, array{0: array<string,mixed>, 1: ProjectDeal, 2: string, 3: array<string,mixed>}>  $matched
     */
    private function buildMatchedSheet(Spreadsheet $book, array $matched): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Matched');

        $columns = [
            ['sheet', 8, Alignment::HORIZONTAL_CENTER, null],
            ['excel_row', 9, Alignment::HORIZONTAL_CENTER, null],
            ['match_kind', 12, Alignment::HORIZONTAL_CENTER, null],
            ['client (excel)', 32, Alignment::HORIZONTAL_LEFT, null],
            ['deal_ident', 12, Alignment::HORIZONTAL_CENTER, null],
            ['deal_name', 36, Alignment::HORIZONTAL_LEFT, null],
            ['excel_date', 13, Alignment::HORIZONTAL_CENTER, self::FMT_DATE],
            ['deal_project_date', 15, Alignment::HORIZONTAL_CENTER, self::FMT_DATE],
            ['excel_dp', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['excel_pel', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['excel_total', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['db_existing_paid', 18, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['final_price', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['will_insert_dp', 14, Alignment::HORIZONTAL_CENTER, null],
            ['will_insert_pel', 15, Alignment::HORIZONTAL_CENTER, null],
            ['fully_paid_flipped', 18, Alignment::HORIZONTAL_CENTER, null],
            ['invoices_marked_paid', 20, Alignment::HORIZONTAL_CENTER, self::FMT_INT],
            ['excel_refund', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['refund_action', 14, Alignment::HORIZONTAL_CENTER, null],
            ['skipped_existing', 16, Alignment::HORIZONTAL_CENTER, self::FMT_INT],
            ['overpaid_gap', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
        ];
        $this->applyColumns($sheet, $columns);

        $r = 2;
        foreach ($matched as [$row, $deal, $kind, $result]) {
            $existingPaid = (float) $deal->transactions->sum('payment_amount');
            $finalPrice = optional($deal->finalQuotation)->fix_price !== null
                ? (float) $deal->finalQuotation->fix_price
                : null;
            $refundAction = $result['refund_inserted']
                ? 'INSERT'
                : ($deal->refund ? 'ALREADY EXISTS' : ($row['refund'] > 0 ? 'SKIP' : ''));
            $sheet->fromArray([[
                $row['sheet'], $row['row'], $kind, $row['client'],
                $deal->identifier_number, $deal->name,
                $row['date'], $deal->project_date,
                $row['dp'], $row['pel'], $row['dp'] + $row['pel'],
                $existingPaid, $finalPrice,
                $result['dp_inserted'] ? 'YES' : '',
                $result['pel_inserted'] ? 'YES' : '',
                $result['fully_paid_flipped'] ? 'YES' : '',
                $result['invoices_marked_paid'],
                $row['refund'] > 0 ? (float) $row['refund'] : null,
                $refundAction,
                $result['skipped_existing'],
                $result['overpaid'] > 0 ? $result['overpaid'] : null,
            ]], null, "A{$r}");
            $r++;
        }

        $this->finalizeDataSheet($sheet, $columns, $r - 1);
    }

    /**
     * @param  array<int, array{0: array<string,mixed>, 1: array<int, ProjectDeal>}>  $ambiguous
     */
    private function buildAmbiguousSheet(Spreadsheet $book, array $ambiguous): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Ambiguous');

        $columns = [
            ['sheet', 8, Alignment::HORIZONTAL_CENTER, null],
            ['excel_row', 9, Alignment::HORIZONTAL_CENTER, null],
            ['client', 32, Alignment::HORIZONTAL_LEFT, null],
            ['excel_date', 13, Alignment::HORIZONTAL_CENTER, self::FMT_DATE],
            ['excel_fee', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['excel_dp', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['excel_pel', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['candidates', 80, Alignment::HORIZONTAL_LEFT, null],
        ];
        $this->applyColumns($sheet, $columns);

        $r = 2;
        foreach ($ambiguous as [$row, $cands]) {
            $sheet->fromArray([[
                $row['sheet'], $row['row'], $row['client'], $row['date'],
                $row['fee'], $row['dp'], $row['pel'],
                implode(' | ', array_map(fn (ProjectDeal $d) => "{$d->identifier_number}:{$d->name}({$d->project_date})", $cands)),
            ]], null, "A{$r}");
            $r++;
        }

        $this->finalizeDataSheet($sheet, $columns, $r - 1);
    }

    /**
     * @param  array<int, array<string, mixed>>  $unmatched
     */
    private function buildUnmatchedSheet(Spreadsheet $book, array $unmatched): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Unmatched');

        $columns = [
            ['sheet', 8, Alignment::HORIZONTAL_CENTER, null],
            ['excel_row', 9, Alignment::HORIZONTAL_CENTER, null],
            ['client', 32, Alignment::HORIZONTAL_LEFT, null],
            ['date', 13, Alignment::HORIZONTAL_CENTER, self::FMT_DATE],
            ['fee', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['dp', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['pel', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['status', 12, Alignment::HORIZONTAL_CENTER, null],
        ];
        $this->applyColumns($sheet, $columns);

        $r = 2;
        foreach ($unmatched as $row) {
            $sheet->fromArray([[
                $row['sheet'], $row['row'], $row['client'], $row['date'],
                $row['fee'], $row['dp'], $row['pel'], $row['status'],
            ]], null, "A{$r}");
            $r++;
        }

        $this->finalizeDataSheet($sheet, $columns, $r - 1);
    }

    /**
     * @param  array<int, array{0: array<string,mixed>, 1: ProjectDeal, 2: float}>  $overpaid
     */
    private function buildOverpaidSheet(Spreadsheet $book, array $overpaid): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Overpaid (DB>Excel)');

        $columns = [
            ['sheet', 8, Alignment::HORIZONTAL_CENTER, null],
            ['excel_row', 9, Alignment::HORIZONTAL_CENTER, null],
            ['client', 32, Alignment::HORIZONTAL_LEFT, null],
            ['deal_ident', 12, Alignment::HORIZONTAL_CENTER, null],
            ['deal_name', 36, Alignment::HORIZONTAL_LEFT, null],
            ['excel_total', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['db_paid', 16, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
            ['db_minus_excel', 18, Alignment::HORIZONTAL_RIGHT, self::FMT_MONEY],
        ];
        $this->applyColumns($sheet, $columns);

        $r = 2;
        foreach ($overpaid as [$row, $deal, $gap]) {
            $dbPaid = (float) $deal->transactions->sum('payment_amount');
            $sheet->fromArray([[
                $row['sheet'], $row['row'], $row['client'],
                $deal->identifier_number, $deal->name,
                $row['dp'] + $row['pel'], $dbPaid, $gap,
            ]], null, "A{$r}");
            $r++;
        }

        $this->finalizeDataSheet($sheet, $columns, $r - 1);
    }

    /**
     * Apply header row + per-column widths only. Body alignment + number formats
     * are applied in finalizeDataSheet against the actual populated range.
     *
     * @param  array<int, array{0:string, 1:int, 2:string, 3:?string}>  $columns
     */
    private function applyColumns(Worksheet $sheet, array $columns): void
    {
        $count = count($columns);
        $header = array_map(fn ($c) => $c[0], $columns);
        $sheet->fromArray([$header], null, 'A1');

        $lastCol = Coordinate::stringFromColumnIndex($count);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(self::COLOR_HEADER);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        foreach ($columns as $idx => [$name, $width, $align, $format]) {
            $letter = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->getColumnDimension($letter)->setWidth($width);
        }
    }

    /**
     * Apply body alignment + number format on the populated range,
     * then zebra stripe, borders, freeze pane, and auto-filter.
     *
     * @param  array<int, array{0:string, 1:int, 2:string, 3:?string}>  $columns
     */
    private function finalizeDataSheet(Worksheet $sheet, array $columns, int $lastRow): void
    {
        $sheet->freezePane('A2');

        if ($lastRow < 2) {
            return;
        }

        $columnCount = count($columns);
        $lastCol = Coordinate::stringFromColumnIndex($columnCount);

        foreach ($columns as $idx => [$name, $width, $align, $format]) {
            $letter = Coordinate::stringFromColumnIndex($idx + 1);
            $bodyRange = "{$letter}2:{$letter}{$lastRow}";
            $sheet->getStyle($bodyRange)->getAlignment()->setHorizontal($align);
            if ($format !== null) {
                $sheet->getStyle($bodyRange)->getNumberFormat()->setFormatCode($format);
            }
        }

        $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");
        $this->applyBorders($sheet, "A1:{$lastCol}{$lastRow}");

        for ($r = 3; $r <= $lastRow; $r += 2) {
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB(self::COLOR_ZEBRA);
        }
    }

    private function applyBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('D1D5DB');
    }
}
