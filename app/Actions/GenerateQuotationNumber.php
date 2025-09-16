<?php

namespace App\Actions;

use App\Services\GeneralService;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectQuotationRepository;

class GenerateQuotationNumber
{
    use AsAction;

    public function handle(?ProjectQuotationRepository $projectQuotationRepo = null): string
    {
        if (! $projectQuotationRepo) {
            $projectQuotationRepo = new ProjectQuotationRepository;
        }

        // get latest quotation
        $latestData = $projectQuotationRepo->list(
            select: 'id,quotation_id',
            limit: 1,
            orderBy: 'created_at DESC'
        )->toArray();

        if (count($latestData) == 0) {
            $nextNumber = 1;
        } else {
            $latestNumber = str_replace(['DF', 'DFF'], '', $latestData[0]['quotation_id']);
            $nextNumber = (int) $latestNumber + 1;
        }

        // convert to sequence number format
        $lengthOfSentence = strlen($nextNumber) < 4 ? 4 : strlen($nextNumber) + 1;
        $nextNumber = (new GeneralService)->generateSequenceNumber(number: $nextNumber, length: $lengthOfSentence);

        $prefix = (new GeneralService)->getSettingByKey('quotation_prefix') ?? 'DF';

        $quotation = "{$prefix}{$nextNumber}";

        return $quotation;
    }
}
