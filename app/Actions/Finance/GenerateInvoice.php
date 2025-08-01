<?php

namespace App\Actions\Finance;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Finance\Models\Invoice;
use Modules\Production\Repository\ProjectDealRepository;

class GenerateInvoice
{
    use AsAction;

    /**
     * Generate invoice file
     * Return url of invoice
     * 
     * @param int $projectDealId
     * @param string $type
     * 
     * @return string
     */
    public function handle(int $projectDealId, string $type): string
    {
        $output = '';

        return $output;
    }
}
