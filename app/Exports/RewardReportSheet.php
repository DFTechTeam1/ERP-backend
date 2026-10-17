<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A single, generic worksheet for the reward report: a titled tab with a bold heading row and a
 * set of pre-built data rows. The business logic (what each sheet contains) lives in
 * ProjectCostService; this class only renders what it is given.
 *
 * Money columns are kept as real numbers and displayed through a per-column Excel number format
 * (see $columnFormats) rather than pre-formatted strings, because the value binder would otherwise
 * coerce a grouped string such as "200.000" back into the number 200.
 *
 * WithStrictNullComparison is required so that a 0 value is written as "0" rather than being skipped
 * as a blank cell (PhpSpreadsheet's default non-strict fromArray treats 0 as equal to null).
 */
class RewardReportSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    /**
     * @param  array<int, string>  $headings  The header row labels.
     * @param  array<int, array<int, mixed>>  $rows  The data rows (one array of cell values per row).
     * @param  array<string, string>  $columnFormats  Map of column letter => Excel number format code
     *                                                (e.g. ['I' => '[$-421]#,##0']).
     * @param  string|null  $autoFilter  Cell range (e.g. "A1:H5") to turn into a filterable header,
     *                                   or null for no auto filter.
     */
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows,
        private readonly array $columnFormats = [],
        private readonly ?string $autoFilter = null,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return $this->columnFormats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        if ($this->autoFilter === null) {
            return [];
        }

        $range = $this->autoFilter;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($range): void {
                $event->sheet->getDelegate()->setAutoFilter($range);
            },
        ];
    }
}
