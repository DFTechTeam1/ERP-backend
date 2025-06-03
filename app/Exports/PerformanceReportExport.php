<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Modules\Hrd\Repository\EmployeePointProjectDetailRepository;
use Modules\Hrd\Repository\EmployeePointProjectRepository;
use Modules\Hrd\Repository\EmployeePointRepository;
use Modules\Hrd\Services\EmployeePointService;

class PerformanceReportExport implements FromView, ShouldAutoSize
{
    private $startDate;

    private $endDate;

    private $employees;

    public function __construct(string $startDate, string $endDate, object $employees)
    {
        $this->startDate = $startDate;

        $this->endDate = $endDate;

        $this->employees = $employees;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function view(): View
    {
        $pointService = new EmployeePointService(
            new EmployeePointRepository,
            new EmployeePointProjectRepository,
            new EmployeePointProjectDetailRepository
        );

        $data = [];
        foreach ($this->employees as $employee) {
            $pointData = $pointService->renderEachEmployeePoint($employee->id, $this->startDate, $this->endDate) ?? [];

            if ($pointData) {
                $data[] = $pointData;
            } else {
                $data[] = [
                    'employee' => $employee,
                    'detail_projects' => [],
                ];
            }
        }

        $startDate = date('d F Y', strtotime($this->startDate));
        $endDate = date('d F Y', strtotime($this->endDate));

        return view('hrd::export-performance-report', compact('data', 'startDate', 'endDate'));
    }
}
