<?php

namespace Modules\Finance\Services;

use App\Data\Finance\FiscalYear\StoreFiscalYearData;
use Modules\Finance\Repository\FiscalYearRepository;

class FiscalYearService
{
    public function __construct(
        private readonly FiscalYearRepository $repo
    ) {}

    public function store(StoreFiscalYearData $payload): array
    {
        try {

            return generalResponse(
                message: "Fiscal year created"
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
