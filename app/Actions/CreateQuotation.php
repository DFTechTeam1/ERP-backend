<?php

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectQuotationRepository;

class CreateQuotation
{
    use AsAction;

    public function handle(array $payload, ProjectQuotationRepository $projectQuotationRepo): ?string
    {
        /**
         * Here we generate the REAL quotation id
         * Given quotation id is not valid in sometime.
         * We can have duplicate number when 2 users create a project in the same time
         */
        $quotationId = GenerateQuotationNumber::run();
        $payload['quotation']['quotation_id'] = $quotationId;

        $quotation = $projectQuotationRepo->store(
            data: collect($payload['quotation'])->except('items')->toArray()
        );

        $quotation->items()->createMany(
            collect($payload['quotation']['items'])->map(function ($item) {
                return [
                    'item_id' => $item,
                ];
            })->toArray()
        );

        if ($payload['request_type'] == 'save_and_download') {
            // generate quotation pdf
            $encrypted = \Illuminate\Support\Facades\Crypt::encryptString(str_replace('#', '', $payload['quotation']['quotation_id']));
            $url = url("quotations/download/{$encrypted}/download");
        }

        return $url ?? null;
    }
}
