<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSinglePicProjectGroupByClass implements FromView, ShouldAutoSize, WithTitle
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
        return view('reports.projects.byClass', [
            'projects' => $this->projects
        ]);
    }

    public function title(): string
    {
        return 'Per Year Per Class ' . $this->year;
    }
}
