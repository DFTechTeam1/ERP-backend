<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class SinglePicProject implements FromView, ShouldAutoSize, WithTitle
{
    public $projects;

    public $year;

    public function __construct(string $year, Collection $projects)
    {
        $this->projects = $projects;

        $this->year = $year;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        return view('reports.projects.singlePic', [
            'projects' => $this->projects
        ]);
    }

    public function title(): string
    {
        return 'Project with Single PIC - ' . $this->year;
    }
}
