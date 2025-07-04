<?php

namespace App\Actions\Finance;

use App\Actions\Finance\GenerateInvoiceContent;
use App\Enums\Transaction\InvoiceStatus;
use App\Services\GeneralService;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Finance\Models\Invoice;
use Modules\Production\Repository\ProjectDealRepository;

class CreateMasterInvoice
{
    use AsAction;

    public function handle(int $projectDealId)
    {
        // generate content
        $repo = new ProjectDealRepository();
        $generalService = new GeneralService();
        
        $projectDeal = $repo->show(
            uid: $projectDealId,
            select: 'id,name,project_date,led_detail,venue,customer_id,city_id,country_id,is_fully_paid,identifier_number',
            relation: [
                'finalQuotation',
                'finalQuotation.items',
                'transactions',
                'city:id,name',
                'country:id,name',
                'customer:id,name'
            ]
        );

        $content = GenerateInvoiceContent::run(deal: $projectDeal, amount: 0, invoiceNumber: '', requestDate: '');

        $invoiceNumber = $generalService->generateInvoiceNumber(identifierNumber: $projectDeal->identifier_number);

        $payload = [
            'amount' => $projectDeal->finalQuotation->fix_price,
            'paid_amount' => 0,
            'payment_due' => $projectDeal->project_date,
            'payment_date' => $projectDeal->project_date,
            'project_deal_id' => $projectDealId,
            'customer_id' => $projectDeal->customer_id,
            'status' => InvoiceStatus::Unpaid,
            'raw_data' => $content,
            'parent_number' => null,
            'number' => $invoiceNumber,
            'is_main' => true,
            'sequence' => 0,
        ];

        Invoice::create($payload);
    }
}
