<?php

namespace App\Exports;

use App\Enums\Company\ExportImportAreaType;
use App\Exports\InventoryPerItemReport;
use App\Services\ExportImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Inventory\Services\InventoryService;

class SummaryInventoryReport implements WithMultipleSheets, ShouldQueue
{
    use Exportable;

    private $data;
    private $userId;
    private $filepath;

    public function __construct(array $data, object $user, string $filepath = '')
    {
        $this->data = $data;
        $this->userId = $user->id;
        $this->filepath = $filepath;
    }

    public function sheets(): array
    {
        $output = [
            new InventoryGeneralReport($this->data)
        ];

        foreach ($this->data['per_item'] as $item) {
            $output[] = new InventoryPerItemReport($item);
        }

        return $output;
    }

    public function failed(\Throwable $exception)
    {
        (new ExportImportService)->handleErrorProcessing(payload: [
            'description' => 'Failed to export finance report',
            'message' => $exception->getMessage(),
            'area' => 'finance',
            'user_id' => $this->userId
        ]);
    }
}
