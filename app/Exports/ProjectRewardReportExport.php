<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Finance\Services\ProjectCostService;

/**
 * The reward disbursement workbook handed to management and HR.
 *
 * It is a thin, multi-sheet wrapper: each entry in the given sheet definitions becomes one
 * {@see RewardReportSheet} tab. All of the business logic (which projects, which rewards and how they
 * are aggregated) lives in {@see ProjectCostService::export()}, which builds
 * the definitions and passes them here, so this class stays purely presentational and easy to test.
 */
class ProjectRewardReportExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array{title: string, headings: array<int, string>, rows: array<int, array<int, mixed>>, formats?: array<string, string>, autoFilter?: string|null}>  $sheets
     *                                                                                                                                                                                  Ordered sheet definitions, each with a tab title, a heading row, its data rows, an optional
     *                                                                                                                                                                                  column => number-format map and an optional auto-filter range.
     */
    public function __construct(private readonly array $sheets) {}

    /**
     * @return array<int, RewardReportSheet>
     */
    public function sheets(): array
    {
        return array_map(
            fn (array $sheet): RewardReportSheet => new RewardReportSheet(
                $sheet['title'],
                $sheet['headings'],
                $sheet['rows'],
                $sheet['formats'] ?? [],
                $sheet['autoFilter'] ?? null,
            ),
            $this->sheets,
        );
    }
}
