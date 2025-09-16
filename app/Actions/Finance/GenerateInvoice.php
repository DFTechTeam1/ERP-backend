<?php

namespace App\Actions\Finance;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Finance\Models\Invoice;

class GenerateInvoice
{
    use AsAction;

    /**
     * Generate invoice file
     * Return url of invoice
     */
    public function handle(int $projectDealId, string $type): string
    {
        $output = '';

        return $output;
    }
}
