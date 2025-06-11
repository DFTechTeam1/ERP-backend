<?php

namespace Modules\Production\Services;

use App\Services\GeneralService;
use Modules\Production\Repository\ProjectDealMarketingRepository;
use Modules\Production\Repository\ProjectDealRepository;

class ProjectDealService
{
    private $repo;

    private $marketingRepo;

    private $generalService;

    /**
     * Construction Data
     */
    public function __construct(
        ProjectDealRepository $repo,
        ProjectDealMarketingRepository $marketingRepo,
        GeneralService $generalService
    ) {
        $this->repo = $repo;

        $this->marketingRepo = $marketingRepo;

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

            $paginated = $paginated->map(function ($item) {

                $marketing = implode(',', $item->marketings->pluck('employee.nickname')->toArray());

                return [
                    'uid' => \Illuminate\Support\Facades\Crypt::encryptString(str_replace('#', '', $item->latestQuotation->quotation_id)), // stand for encrypted of latest quotation id
                    'name' => $item->name,
                    'venue' => $item->venue,
                    'project_date' => $item->formatted_project_date,
                    'city' => $item->city ? $item->city->name : '-',
                    'collaboration' => $item->collboration ?? '-',
                    'status' => $item->status_text,
                    'status_color' => $item->status_color,
                    'marketing' => $marketing,
                    'down_payment' => $item->getDownPaymentAmount(formatPrice: true),
                    'remaining_payment' => $item->getRemainingPayment(formatPrice: true),
                    'status' => true,
                    'fix_price' => $item->getFinalPrice(formatPrice: true),
                    'latest_price' => $item->getLatestPrice(formatPrice: true),
                    'is_fully_paid' => (bool) $item->is_fully_paid,
                    'status_payment' => $item->getStatusPayment(),
                    'status_payment_color' => $item->getStatusPaymentColor(),
                    'quotation' => [
                        'id' => $item->latestQuotation->quotation_id,
                        'fix_price' => "Rp" . number_format(num: $item->latestQuotation->fix_price, decimal_separator: ','),
                    ],
                ];
            });

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

    public function getPriceFormula()
    {
        $setting = $this->generalService->getSettingByKey(param: 'area_guide_price');

        $output = [];

        if ($setting) {
            $master = json_decode($setting, true);

            $mainBallroom = [];
            $keys = [
                'Main Ballroom Fee',
                'Prefunction Fee',
                'Max Discount',
            ];
            foreach ($master['area'] as $area) {
                if (in_array($area['area'], $keys)) {

                }
            }

            $output = [
                'main_ballroom' => '',
                'prefunction' => '',
                'equipment' => '',
                'discount' => '',
                'price_up' => '',
            ];
        }
    }
}
