<?php

namespace App\Imports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class InventoryGeneralReport implements FromView, WithTitle, ShouldAutoSize
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('reports.inventory.inventory_general', [
            'data' => $this->data
        ]);
    }

    public function title(): string
    {
        return 'General Report';
    }
}
