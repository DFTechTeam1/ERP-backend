<?php

namespace App\Exports;

use App\Services\ExportImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SummaryInventoryReport implements ShouldQueue, WithMultipleSheets
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
            new InventoryGeneralReport($this->data),
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
            'user_id' => $this->userId,
        ]);
    }
}
