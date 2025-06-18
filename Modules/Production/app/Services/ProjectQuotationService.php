<?php

namespace Modules\Production\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Modules\Production\Repository\ProjectQuotationRepository;

class ProjectQuotationService
{
    private $repo;

    private $generalService;

    /**
     * Construction Data
     */
    public function __construct(
        ProjectQuotationRepository $repo,
        \App\Services\GeneralService $generalService
    )
    {
        $this->repo = $repo;

        $this->generalService = $generalService;
    }

    /**
     * Get list of data
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (! empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );
            $totalData = $this->repo->list('id', $where)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'name,uid,id');

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store data
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array {
        try {
            $this->repo->update($data, $id);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     *
     *
     * @return void
     */
    public function delete(int $id): array
    {
        try {
            return generalResponse(
                'Success',
                false,
                $this->repo->delete($id)->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
     */
    public function bulkDelete(array $ids): array
    {
        try {
            $this->repo->bulkDelete($ids, 'uid');

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function generateQuotation(string $quotationId, string $type): Response
    {
        $quotationId = Crypt::decryptString($quotationId);
        
        $data = $this->repo->show(
            uid: 'uid',
            select: 'id,project_deal_id,fix_price,quotation_id,description',
            relation: [
                'deal:id,name,project_date,customer_id,event_type,venue,collaboration,led_detail,country_id,state_id,city_id,project_class_id',
                'deal.city:id,name',
                'deal.country:id,name',
                'deal.state:id,name',
                'deal.customer:id,name',
                'deal.class:id,name',
                'items:quotation_id,id,item_id',
                'items.item:id,name'
            ],
            where: "quotation_id = '{$quotationId}'"
        );

        $output = [
            'rules' => $this->generalService->getSettingByKey('quotation_rules'),
            'company' => [
                'address' => $this->generalService->getSettingByKey('company_address'),
                'email' => $this->generalService->getSettingByKey('company_email'),
                'phone' => $this->generalService->getSettingByKey('company_phone'),
                'name' => $this->generalService->getSettingByKey('company_name'),
            ],
            'quotationNumber' => "#{$quotationId}",
            'date' => date('d F Y', strtotime($data->deal->project_date)),
            'designJob' => $data->deal->class->name,
            'price' => 'Rp' . number_format(num: $data->fix_price, decimal_separator: ','),
            'client' => [
                'name' => $data->deal->customer->name,
                'city' => $data->deal->city->name,
                'country' => $data->deal->country->name
            ],
            'event' => [
                'title' => $data->deal->name,
                'date' => date('d F Y', strtotime($data->deal->project_date)),
                'venue' => $data->deal->venue
            ],
            'ledDetails' => collect($data->deal->led_detail)->map(function ($item) {
                return [
                    'name' => $item['name'] == 'main' ? 'Main Stage' : 'Prefunction',
                    'size' => $item['textDetail']
                ];
            })->toArray(),
            'items' => collect($data->items)->map(function ($item) {
                return $item->item->name;
            })->toArray()
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('quotation.quotation', $output)
        ->setPaper('14')
        ->setOption([
            'defaultFont' => 'sans-serif',
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'debugPng' => false,
            'debugLayout' => false,
            'debugCss' => false
        ]);

        $filename = "{$data->deal->name}.pdf";

        if ($type == 'stream') {
            return $pdf->stream($filename);
        } else {
            return $pdf->download($filename);
        }
    }
}
