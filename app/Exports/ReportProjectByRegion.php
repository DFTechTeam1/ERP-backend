<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportProjectByRegion implements FromView, ShouldAutoSize, WithTitle
{
    public $year;

    public $projects;

    public function __construct(string $year, \Illuminate\Support\Collection $projects)
    {
        $this->year = $year;
        $this->projects = $projects;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        return view('reports.projects.byRegion', [
            'projects' => $this->projects
        ]);
    }

    public function title(): string
    {
        return 'By Region - ' . $this->year;
    }
}
