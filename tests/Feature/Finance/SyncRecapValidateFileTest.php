<?php

/**
 * File-shape validation — runs before any parsing or DB touching.
 * Guards against wrong extension, corrupted upload, missing year sheet,
 * and missing/renamed columns.
 */

use Illuminate\Console\OutputStyle;
use Modules\Finance\Console\SyncRecapTransactionsCommand;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function makeXlsx(array $sheets): string
{
    // $sheets: ['2025' => ['header' => [...], 'rows' => [...]]  ...]
    $book = new Spreadsheet;
    $first = true;
    foreach ($sheets as $name => $data) {
        $sheet = $first ? $book->getActiveSheet() : $book->createSheet();
        $first = false;
        $sheet->setTitle((string) $name);
        // header lives at row 2 in the real recap
        $sheet->fromArray([['ignored']], null, 'A1');
        $sheet->fromArray([$data['header']], null, 'A2');
        if (! empty($data['rows'])) {
            $sheet->fromArray($data['rows'], null, 'A3');
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'recap_').'.xlsx';
    (new Xlsx($book))->save($path);

    return $path;
}

beforeEach(function () {
    $this->cmd = new SyncRecapTransactionsCommand;
    // The validator uses $this->info() for success — silence it in tests by
    // giving the command a real (throwaway) output.
    $this->cmd->setOutput(new OutputStyle(
        new ArrayInput([]),
        new BufferedOutput
    ));
});

afterEach(function () {
    // Best-effort tempfile cleanup.
    foreach (glob(sys_get_temp_dir().'/recap_*.xlsx') ?: [] as $f) {
        @unlink($f);
    }
});

it('rejects a missing file with an actionable message', function () {
    $result = $this->cmd->validateRecapFile('/nonexistent/definitely.xlsx', ['2025']);
    expect($result)
        ->not->toBeNull()
        ->toContain('File not readable')
        ->toContain('storage/app/recap.xlsx');
});

it('rejects a non-xlsx extension', function () {
    $csv = tempnam(sys_get_temp_dir(), 'recap_').'.csv';
    file_put_contents($csv, "not,an,xlsx\n");

    $result = $this->cmd->validateRecapFile($csv, ['2025']);
    @unlink($csv);

    expect($result)
        ->not->toBeNull()
        ->toContain('Expected a .xlsx file');
});

it('rejects a file that looks like xlsx but cannot be opened', function () {
    $fake = tempnam(sys_get_temp_dir(), 'recap_').'.xlsx';
    file_put_contents($fake, 'this is not a real xlsx payload');

    $result = $this->cmd->validateRecapFile($fake, ['2025']);
    @unlink($fake);

    expect($result)
        ->not->toBeNull()
        ->toContain('Could not open');
});

it('rejects when no requested year sheet is present', function () {
    $path = makeXlsx([
        '2021' => ['header' => ['No', 'Client', 'Tanggal', 'Pembayaran DP', 'Pelunasan'], 'rows' => []],
    ]);

    $result = $this->cmd->validateRecapFile($path, ['2025', '2026']);
    expect($result)
        ->not->toBeNull()
        ->toContain('None of the requested year sheets')
        ->toContain('Requested: 2025, 2026')
        ->toContain('Available: 2021');
});

it('rejects when required columns are missing from a year sheet', function () {
    $path = makeXlsx([
        // Missing "Pembayaran DP" and "Pelunasan"
        '2025' => ['header' => ['No', 'Client', 'Tanggal'], 'rows' => []],
    ]);

    $result = $this->cmd->validateRecapFile($path, ['2025']);
    expect($result)
        ->not->toBeNull()
        ->toContain('[2025] missing:')
        ->toContain('pembayaran dp')
        ->toContain('pelunasan');
});

it('accepts a well-formed file', function () {
    $path = makeXlsx([
        '2025' => [
            'header' => ['No', 'Status', 'Marketing', 'Tanggal', 'Client ', 'Event', 'Venue', 'Kota', 'EO', 'Ukuran LED', 'Fee', 'Pembayaran DP', 'Pelunasan'],
            'rows' => [[1, 'Confirm', 'Sharon', '2025-01-05', 'Antoni Michelle', '', 'Westin', 'Surabaya', 'Nuansa', '', 90000000, 45000000, 45000000]],
        ],
    ]);

    expect($this->cmd->validateRecapFile($path, ['2025', '2026']))->toBeNull();
});

it('accepts a file where only some requested years are present', function () {
    $path = makeXlsx([
        '2025' => ['header' => ['Client', 'Tanggal', 'Pembayaran DP', 'Pelunasan'], 'rows' => []],
    ]);

    // 2026 requested but missing — that's fine as long as 2025 is there.
    expect($this->cmd->validateRecapFile($path, ['2025', '2026']))->toBeNull();
});
