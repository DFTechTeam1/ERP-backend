<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Inventory\Services\InventoryService;

class SummaryInventoryReport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $service = app(InventoryService::class);

        $data = $service->getInventoriesTree();

        return [
            new InventoryGeneralReport($data)
        ];
    }
}
