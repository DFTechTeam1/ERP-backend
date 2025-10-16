<?php

namespace Modules\Production\Services;

use App\Actions\CopyDealToProject;
use App\Actions\CreateQuotation;
use App\Enums\Cache\CacheKey;
use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Enums\Production\ProjectDealChangeStatus;
use App\Enums\Production\ProjectDealStatus;
use App\Enums\Transaction\InvoiceStatus;
use App\Enums\Transaction\TransactionType;
use App\Services\EncryptionService;
use App\Services\GeneralService;
use App\Services\Geocoding;
use App\Services\NasFolderCreationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Jobs\NotifyRequestPriceChangesHasBeenApproved;
use Modules\Finance\Jobs\NotifyRequestPriceChangesJob;
use Modules\Finance\Jobs\ProjectHasBeenFinal;
use Modules\Finance\Repository\InvoiceRepository;
use Modules\Finance\Repository\PriceChangeReasonRepository;
use Modules\Finance\Repository\ProjectDealPriceChangeRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Jobs\NotifyApprovalProjectDealChangeJob;
use Modules\Production\Jobs\NotifyProjectDealChangesJob;
use Modules\Production\Jobs\ProjectDealCanceledJob;
use Modules\Production\Repository\ProjectDealMarketingRepository;
use Modules\Production\Repository\ProjectDealRepository;
use Modules\Production\Repository\ProjectQuotationRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectDealChangeRepository;

class ProjectDealService
{
    private $repo;

    private $marketingRepo;

    private $generalService;

    private $projectQuotationRepo;

    private $projectRepo;

    private $geocoding;

    private $projectDealChangeRepo;

    private $projectDealPriceChangeRepo;

    private InvoiceRepository $invoiceRepo;

    private PriceChangeReasonRepository $priceChangeReasonRepo;

    private EmployeeRepository $employeeRepo;

    private InteractiveRequestRepository $interactiveRequestRepo;

    private InteractiveProjectRepository $interactiveProjectRepo;

    private NasFolderCreationService $nasFolderCreationService;
    /**
     * Construction Data
     */
    public function __construct(
        ProjectDealRepository $repo,
        ProjectDealMarketingRepository $marketingRepo,
        GeneralService $generalService,
        ProjectQuotationRepository $projectQuotationRepo,
        ProjectRepository $projectRepo,
        Geocoding $geocoding,
        ProjectDealChangeRepository $projectDealChangeRepo,
        ProjectDealPriceChangeRepository $projectDealPriceChangeRepo,
        InvoiceRepository $invoiceRepo,
        PriceChangeReasonRepository $priceChangeReasonRepo,
        EmployeeRepository $employeeRepo,
        InteractiveRequestRepository $interactiveRequestRepo,
        InteractiveProjectRepository $interactiveProjectRepo,
        NasFolderCreationService $nasFolderCreationService,
    ) {
        $this->projectDealChangeRepo = $projectDealChangeRepo;

        $this->projectDealPriceChangeRepo = $projectDealPriceChangeRepo;

        $this->priceChangeReasonRepo = $priceChangeReasonRepo;

        $this->repo = $repo;

        $this->marketingRepo = $marketingRepo;

        $this->generalService = $generalService;

        $this->projectQuotationRepo = $projectQuotationRepo;

        $this->projectRepo = $projectRepo;

        $this->geocoding = $geocoding;

        $this->invoiceRepo = $invoiceRepo;

        $this->employeeRepo = $employeeRepo;

        $this->interactiveRequestRepo = $interactiveRequestRepo;

        $this->interactiveProjectRepo = $interactiveProjectRepo;

        $this->nasFolderCreationService = $nasFolderCreationService;
    }

    /**
     * Get list of data
     * 
     * Filter can be:
     * - multiple name
     * - muliple customer name
     * - project date (start date, end date)
     * - multiple status
     * - range price
     * - multiple marketing
     * 
     * @return array
     */
    public function list (
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array {
        try {
            $user = Auth::user();

            $itemsPerPage = request('itemsPerPage') ?? 10;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $where = "deleted_at is null";
            $whereHas = [];

            if (request('event')) {
                $name = request('event');
                $where .= " AND name like '%{$name}%'";
            }

            if (request('customer')) {
                $customer = request('customer');
                $customerIds = implode(',', $customer);
                $whereHas[] = [
                    'relation' => 'customer',
                    'query' => "id IN ({$customerIds})"
                ];
            }

            if (request('status')) {
                $status = request('status');
                $statusIds = collect($status)->pluck('id')->implode(',');
                $where .= " AND status IN ({$statusIds})";
            } else {
                $where .= " AND status != " . ProjectDealStatus::Canceled->value;
            }

            if (request('date')) {
                $dateSplit = explode(' - ', request('date'));
                if (isset($dateSplit[1])) {
                    $where .= " AND project_date BETWEEN '" . $dateSplit[0] . "' AND '" . $dateSplit[1] . "'";
                } else if (!isset($dateSplite[1]) && isset($dateSplit[0])) {
                    $where .= " AND project_date = '" . $dateSplit[0] . "'";
                }
            }

            if (request('price')) {
                $price = request('price');
                $whereHas[] = [
                    'relation' => 'latestQuotation',
                    'query' => "fix_price BETWEEN " . $price[0] . " AND " . $price[1]
                ];
            }

            if (request('marketing')) {
                $marketing = request('marketing') ?? [];
                
                $marketingIds = collect($marketing)->map(function ($itemMarketing) {
                    $id = $this->generalService->getIdFromUid($itemMarketing['uid'], new \Modules\Hrd\Models\Employee());

                    return $id;
                })->toArray();
                $marketingIds = implode(',', $marketingIds);

                $whereHas[] = [
                    'relation' => 'marketings',
                    'query' => "employee_id IN ({$marketingIds})"
                ];
            }

            $sorts = '';
            if (! empty(request('sortBy'))) {
                foreach (request('sortBy') as $sort) {
                    if ($sort['key'] == 'task_name') {
                        $sort['key'] = 'name';
                    }
                    if ($sort['key'] != 'pic' && $sort['key'] != 'uid') {
                        $sorts .= $sort['key'].' '.$sort['order'].',';
                    }
                }

                $sorts = rtrim($sorts, ',');
            } else {
                $sorts .= 'created_at desc';
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $whereHas,
                $sorts
            );
            $totalData = $this->repo->list(select: 'id', where: $where, whereHas: $whereHas)->count();

            $paginated = $paginated->map(function ($item) use ($user) {

                $marketing = implode(',', $item->marketings->pluck('employee.nickname')->toArray());

                $isCancel = $item->status == ProjectDealStatus::Canceled ? true : false;
                $isHaveActiveRequestChanges = $item->activeProjectDealChange ? true : false;

                // define status. If project deal have a request price changes, wee need to make status as 'Waiting for approval', otherwise show the real status
                $isHaveRequestPriceChanges = $item->activeProjectDealPriceChange ? true : false;
                $canRequestPriceChanges = ! $isHaveRequestPriceChanges && $item->status == ProjectDealStatus::Final ? true : false;
                if ($isHaveRequestPriceChanges) {
                    $status = __('notification.waitingForApproval');
                    $statusColor = 'grey-darken-2';
                } else {
                    $status = $item->status->label();
                    $statusColor = $item->status->color();
                }

                $finalPrice = $item->getFinalPrice(formatPrice: true);
                $newPrice = $isHaveRequestPriceChanges ? $item->activeProjectDealPriceChange->new_price : 0;
                if ($newPrice > 0) {
                    $newPrice = 'Rp'.number_format(num: $newPrice, decimal_separator: ',');
                }

                return [
                    'uid' => \Illuminate\Support\Facades\Crypt::encryptString($item->id), // stand for encrypted of latest quotation id
                    'latest_quotation_id' => \Illuminate\Support\Facades\Crypt::encryptString($item->latestQuotation->quotation_id),
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
                    'remaining_payment_raw' => $item->getRemainingPayment(),
                    'status' => true,
                    'status_project' => $status,
                    'status_project_color' => $statusColor,
                    'fix_price' => $finalPrice,
                    'new_price' => $newPrice,
                    'latest_price' => $item->getLatestPrice(formatPrice: true),
                    'is_fully_paid' => (bool) $item->is_fully_paid,
                    'status_payment' => $item->getStatusPayment(),
                    'status_payment_color' => $item->getStatusPaymentColor(),
                    'can_make_payment' => $item->canMakePayment() && !$isCancel,
                    'can_publish_project' => $item->canPublishProject() && !$isCancel,
                    'can_make_final' => $item->canMakeFinal() && !$isCancel,
                    'can_edit' => (bool) !$isCancel && !$isHaveActiveRequestChanges,
                    'can_delete' => (bool) !$item->isFinal() && !$isCancel,
                    'can_cancel' => $item->status == ProjectDealStatus::Temporary ? true : false,
                    'can_approve_event_changes' => $isHaveActiveRequestChanges && $user->hasPermissionTo('approve_project_deal_change') ? true : false,
                    'can_reject_event_changes' => $isHaveActiveRequestChanges && $user->hasPermissionTo('reject_project_deal_change') ? true : false,
                    'is_final' => $item->status == ProjectDealStatus::Final ? true : false,
                    'quotation' => [
                        'id' => $item->latestQuotation->quotation_id,
                        'fix_price' => "Rp" . number_format(num: $item->latestQuotation->fix_price, decimal_separator: ','),
                    ],
                    'unpaidInvoices' => $item->unpaidInvoices->map(function ($invoice) {
                        return [
                            'id' => \Illuminate\Support\Facades\Crypt::encryptString($invoice->id),
                            'number' => $invoice->number,
                            'amount' => $invoice->amount,
                        ];
                    }),
                    'can_request_price_changes' => $canRequestPriceChanges,
                    'have_request_changes' => $isHaveActiveRequestChanges,
                    'have_price_changes' => $isHaveRequestPriceChanges,
                    'can_approve_price_changes' => $isHaveRequestPriceChanges ? true : false,
                    'can_reject_price_changes' => $isHaveRequestPriceChanges ? true : false,
                    'changes_id' => $isHaveActiveRequestChanges ? Crypt::encryptString($item->activeProjectDealChange->id) : null,
                    'price_changes_id' => $isHaveRequestPriceChanges ? Crypt::encryptString($item->activeProjectDealPriceChange->id) : null,
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
     * @return array
     */
    public function delete(string $id): array
    {
        DB::beginTransaction();
        try {
            $detail = $this->repo->show(uid: (string) \Illuminate\Support\Facades\Crypt::decryptString($id), select: 'id,name,status', relation: [
                'marketings',
                'quotations',
                'quotations.items',
                'project:id,project_deal_id'
            ]);

            // only not finalized project that can be deleted
            if ($detail->isFinal()) return errorResponse('Cannot delete finalized project');

            foreach ($detail->quotations as $quotation) {
                $quotation->items()->delete();
            }

            $detail->quotations()->delete();
            $detail->marketings()->delete();

            if ($detail->project && config('app.env') !== 'testing') {
                // create nas delete request
                $this->nasFolderCreationService->sendRequest(
                    payload: [
                        "project_id" => $detail->project->id,
                    ],
                    type: 'delete'
                );
            }

            $this->repo->delete(id: $detail->id);

            DB::commit();

            return generalResponse(
                __('notification.successDeleteProjectDeal'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

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

    /**
     * Create new quotation data in existing deal
     * 
     * @param array $payload
     * @param string $projectDealId
     * 
     * @return array
     */
    public function createNewQuotation(array $payload, string $projectDealId): array
    {
        DB::beginTransaction();

        try {
            $projectDeal = $this->repo->show(
                uid: $projectDealId,
                select: 'id,is_fully_paid',
                relation: [
                    'finalQuotation:id,project_deal_id',
                ]
            );
            
            if ($projectDeal->finalQuotation) {
                return errorResponse(message: __('notification.quotationAlreadyFinal'));
            }

            $payload['quotation']['project_deal_id'] = $projectDeal->id;
            $url = CreateQuotation::run($payload, $this->projectQuotationRepo);

            DB::commit();

            return generalResponse(
                message: "Success",
                data: [
                    'url' => $url
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Publish project deal
     * 
     * @param string $projectDealId
     * @param string $type
     * 
     * @return array
     */
    public function publishProjectDeal(string $projectDealId, string $type): array
    {
        DB::beginTransaction();
        try {
            $projectDealId = Crypt::decryptString($projectDealId);

            $payload = [
                'status' => $type === 'publish' ? \App\Enums\Production\ProjectDealStatus::Temporary->value : \App\Enums\Production\ProjectDealStatus::Final->value,
                // 'identifier_number' => $this->generalService->setProjectIdentifier()
            ];

            $this->repo->update(
                data: $payload,
                id: $projectDealId,
            );

            if ($type === 'publish_final') {
                // update quotation to final
                $detail = $this->repo->show(
                    uid: $projectDealId,
                    select: 'id,name,project_date,customer_id,event_type,venue,collaboration,note,led_area,led_detail,country_id,state_id,city_id,project_class_id,longitude,latitude,status',
                    relation: [
                        'latestQuotation',
                        'city:id,name',
                        'state:id,name',
                        'class:id,name',
                        'marketings:id,project_deal_id,employee_id'
                    ]
                );

                $this->projectQuotationRepo->update(
                    data: [
                        'is_final' => 1,
                    ],
                    id: $detail->latestQuotation->id
                );

                $project = CopyDealToProject::run($detail, $this->generalService);

                // generate master invoice
                \App\Actions\Finance\CreateMasterInvoice::run(projectDealId: $projectDealId);

                ProjectHasBeenFinal::dispatch($projectDealId)->afterCommit();
            }

            DB::commit();

            // call NAS service
            if (config('app.env') !== 'testing') {
                $this->nasFolderCreationService->sendRequest(
                    payload: [
                        "project_id" => $project->id,
                        "project_name" => $project->name,
                        "project_date" => $project->project_date,
                    ]
                );
            }

            return generalResponse(
                message: __('notification.successPublishProjectDeal'),
                data: [
                    'project' => $project ?? null
                ]
            );                                                                                                           
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorResponse($th);
        }
    }

    public function detailProjectDealForEdit(string $quotationId): array
    {
        $data = $this->repo->show(
            uid: Crypt::decryptString($quotationId),
            select: 'id,name,project_date,customer_id,event_type,venue,collaboration,note,led_area,led_detail,country_id,state_id,city_id,project_class_id,is_high_season,equipment_type,status,include_tax',
            relation: [
                'marketings:id,project_deal_id,employee_id',
                'marketings.employee:id,uid',
                'latestQuotation',
                'latestQuotation.items:id,quotation_id,item_id',
                'latestQuotation.items.item:id,name'
            ]
        );

        $items = $data->latestQuotation->items->map(function ($item) {
            return [
                'value' => $item->item->id,
                'title' => $item->item->name
            ];
        });
        $data['quotation_items'] = $items;
        $data['is_final'] = $data->status == ProjectDealStatus::Final ? true : false;

        return generalResponse(
            message: "Success",
            data: $data->toArray()
        );
    }

    /**
     * Get detail of project deal
     * 
     * @param string $quotationId
     * 
     * @return array
     */
    public function detailProjectDeal(string $projectDealUid): array
    {
        try {
            $user = Auth::user();
            $projectDealUidRaw = Crypt::decryptString($projectDealUid);
            $isEdit = request('edit');

            // get detail of project deal without the transactions
            if ($isEdit) {
                return $this->detailProjectDealForEdit(quotationId: $projectDealUid);
            }

            $data = $this->repo->show(
                uid: $projectDealUidRaw,
                select: "id,name,project_date,customer_id,event_type,venue,collaboration,project_class_id,city_id,note,led_detail,is_fully_paid,status,cancel_reason,cancel_at",
                relation: [
                    'transactions',
                    'transactions.invoice:id,number,parent_number,paid_amount,payment_date,uid',
                    'transactions.attachments:id,transaction_id,image',
                    'quotations',
                    'quotations.items:id,quotation_id,item_id',
                    'quotations.items.item:id,name',
                    'latestQuotation',
                    'finalQuotation',
                    'customer:id,name,phone,email',
                    'city:id,name',
                    'class:id,name',
                    'invoices' => function ($queryInvoice) {
                        $queryInvoice->where('is_main', 0)
                            ->with([
                                'transaction:id,invoice_id,created_at,transaction_date',
                                'pendingUpdate:id,invoice_id'
                            ]);
                    }
                ]
            );

            $led = collect($data->led_detail)->groupBy('name');
            $main = [];
            $prefunction = [];

            if (isset($led['main'])) {
                foreach ($led['main'] as $ledMain) {
                    foreach ($ledMain['led'] as $ledDetail) {
                        $main[] = [
                            'width' => $ledDetail['width'],
                            'height' => $ledDetail['height'],
                        ];
                    }
                }
            }

            if (isset($led['prefunction'])) {
                foreach ($led['prefunction'] as $ledPrefunction) {
                    foreach ($ledPrefunction['led'] as $ledDetailPrefunction) {
                        $prefunction[] = [
                            'width' => $ledDetailPrefunction['width'],
                            'height' => $ledDetailPrefunction['height'],
                        ];
                    }
                }
            }

            $quotations = collect($data->quotations)->map(function ($item) use ($data, $main, $prefunction) {
                return [
                    'id' => Crypt::encryptString($item->id),
                    'quotation_id' => Crypt::encryptString($item->quotation_id),
                    'quotation_number' => $item->quotation_id,
                    'name' => $data->name,
                    'venue' => $data->venue,
                    'price' => $item->fix_price,
                    'is_final' => (bool) $item->is_final,
                    'design_job' => $item->design_job,
                    'detail' => [
                        'office'=> [
                            'logo' => asset('storage/settings/' . $this->generalService->getSettingByKey('company_logo')),
                            'address' => $this->generalService->getSettingByKey('company_address'),
                            'phone' => $this->generalService->getSettingByKey('company_phone'),
                            'email' => $this->generalService->getSettingByKey('company_email'),
                            'name' => $this->generalService->getSettingByKey('company_name')
                        ],
                        'customer' => [
                            'name' => $data->customer->name,
                            'place' => $data->city->name
                        ],
                        'quotation_number' => $item->quotation_id,
                        'event' => [
                            'project_date' => date('d F Y', strtotime($data->project_date)),
                            'event_class' => $data->class->name,
                            'venue' => $data->venue,
                            'led' => [
                                'main' => $main,
                                'prefunction' => $prefunction,
                            ],
                            'itemPreviews' => collect($item->items)->pluck('item.name')->toArray(),
                            'price' => $item->fix_price,
                            'name' => $data->name
                        ],
                        'note' => $item->description,
                        'rules' => '
                            <ul style="list-style: circle; padding-left: 10px; padding-top: 0; font-size: 12px;">
                                <li>Minimum Down Payment sebesar 50% dari total biaya yang ditagihkan, biaya tersebut tidak dapat dikembalikan.</li>
                                <li>Pembayaran melalui rekening BCA 188 060 1225 a/n Wesley Wiyadi / Edwin Chandra Wijaya</li>
                                <li>Biaya diatas tidak termasuk pajak.</li>
                                <li>Biaya layanan diatas hanya termasuk perlengkapan multimedia DFACTORY dan tidak termasuk persewaan unit LED dan sistem multimedia lainnya bila diperlukan.</li>
                                <li>Biaya diatas termasuk Akomodasi untuk Crew bertugas di hari-H event.</li>
                            </ul>
                        '
                    ]
                ];
            })->toArray();

            // get the final quotation
            $finalQuotation = collect([]);
            $products = [];
            $main = [];
            $prefunction = [];
            if ($data->finalQuotation) {
                $finalQuotation = $data->quotations->filter(fn($value) => $value->is_final)->values()[0];

                $finalQuotation['quotation_id'] = Crypt::encryptString($finalQuotation->quotation_id);
                
                $finalQuotation['remaining'] = $data->getRemainingPayment();
            }

            // define products
            $outputLed = collect($data->led_detail)->groupBy('name');
            if (isset($outputLed['main'])) {
                $main = [
                    'product' => 'Main Stage',
                    'description' => collect($outputLed['main'])->sum('totalRaw') . ' m<sup>2</sup>',
                    'amount' => $data->latestQuotation->main_ballroom
                ];

                $products[] = $main;
            }

            if (isset($outputLed['prefunction'])) {
                $prefunction = [
                    'product' => 'Prefunction',
                    'description' => collect($outputLed['prefunction'])->sum('totalRaw') . ' m<sup>2</sup>',
                    'amount' => $data->latestQuotation->prefunction
                ];

                $products[] = $prefunction;
            }

            if ($data->latestQuotation->equipment_fee > 0) {
                $products[] = [
                    'product' => 'Equipment',
                    'description' => '',
                    'amount' => $data->latestQuotation->equipment_fee
                ];
            }

            $transactions = $data->transactions->map(function ($trx) use ($data) {
                $trx['description'] = 'Receiving invoice payment';
                $trx['images'] = collect($trx->attachments)->pluck('real_path')->toArray();
                $trx['customer'] = [
                    'name' => $data->customer->name
                ];
                $trx['invoice_date'] = date('d F Y', strtotime($trx->invoice->payment_date));
                $trx['payment_date'] = date('d F Y', strtotime($trx->created_at));

                return $trx;
            })->values();

            // we need to encrypt this data to keep it safe
            $invoiceList = $data->invoices->map(function ($invoice, $key) use ($projectDealUid, $user) {
                $invoiceUrl = \Illuminate\Support\Facades\URL::signedRoute(
                    name: 'invoice.download.type',
                    parameters: [
                        'type' => $invoice->status == InvoiceStatus::Unpaid ? 'collection' : 'proof_of_payment',
                        'projectDealUid' => $projectDealUid,
                        'invoiceUid' => $invoice->uid,
                        'amount' => $invoice->amount,
                        'paymentDate' => $invoice->payment_date
                    ],
                    expiration: now()->addHours(5)
                );

                // define action in each invoice
                $canEditInvoice = true;
                $canDeleteInvoice = true;
                $canApproveChanges = (bool) $user->hasPermissionTo('approve_invoice_changes') && $invoice->status == InvoiceStatus::WaitingChangesApproval;
                $canRejectChanges = (bool) $user->hasPermissionTo('reject_invoice_changes') && $invoice->status == InvoiceStatus::WaitingChangesApproval;

                if ($invoice->status == InvoiceStatus::WaitingChangesApproval || $invoice->status == InvoiceStatus::Paid) {
                    $canEditInvoice = false;
                    $canDeleteInvoice = false;
                }

                return [
                    'type_invoice' => $key == 0 ? 'down_payment' : 'invoice',
                    'id' => \Illuminate\Support\Facades\Crypt::encryptString($invoice->id),
                    'uid' => $invoice->uid,
                    'amount' => $invoice->amount,
                    'paid_amount' => $invoice->paid_amount,
                    'status' => $invoice->status->label(),
                    'status_color' => $invoice->status->color(),
                    'payment_due' => date('d F Y', strtotime($invoice->payment_due)),
                    'billing_date' => date('d F Y', strtotime($invoice->payment_date)),
                    'number' => $invoice->number,
                    'can_edit_invoice' => $canEditInvoice,
                    'can_delete_invoice' => $canDeleteInvoice,
                    'can_approve_invoice' => $canApproveChanges,
                    'can_reject_invoice' => $canRejectChanges,
                    'need_to_pay' => $invoice->status == \App\ENums\Transaction\InvoiceStatus::Unpaid ? true : false,
                    'paid_at' => $invoice->transaction ? date('d F Y', strtotime($invoice->transaction->transaction_date)) : '-',
                    'invoice_url' => $invoiceUrl,
                    'pending_update_id' => $invoice->pendingUpdate ? $invoice->pendingUpdate->id : null,
                    'is_down_payment' => $invoice->is_down_payment,
                ];
            });
            
            $encryptionService = new EncryptionService();
            $invoiceList = $encryptionService->encrypt(string: json_encode($invoiceList), key: config('app.salt_key_encryption'));

            // generate general invoice download url
            $generalInvoiceUrl = URL::signedRoute(
                name: 'invoice.general.download',
                parameters: [
                    'i' => \Illuminate\Support\Facades\Crypt::encryptString($projectDealUidRaw)
                ],
                expiration: now()->addHours(6)
            );
            // encrypt the url
            $generalInvoiceUrl = $encryptionService->encrypt(
                string: json_encode(['url' => $generalInvoiceUrl]),
                key: config('app.salt_key_encryption'),
            );

            $isFinal = $data->isFinal();

            $output = [
                'customer' => [
                    'name' => $data->customer->name,
                    'phone' => $data->customer->phone,
                    'email' => $data->customer->email,
                ],
                'uid' => $projectDealUid,
                'products' => $products,
                'name' => $data->name,
                'final_quotation' => $finalQuotation,
                'transactions' => $transactions,
                'quotations' => $quotations,
                'remaining_payment_raw' => $data->getRemainingPayment(),
                'latest_quotation_id' => $finalQuotation->count() > 0 ? $finalQuotation['quotation_id'] : Crypt::encryptString($data->latestQuotation->quotation_id),
                'is_final' => $isFinal,
                'can_add_more_quotation' => !$isFinal && $data->status != ProjectDealStatus::Canceled,
                'is_cancel' => $data->status == ProjectDealStatus::Canceled ? true : false,
                'cancel_reason' => __('notification.eventHasBeenCancelBecause', ['reason' => $data->cancel_reason]),
                'cancel_at' => $data->cancel_at ? date('d F Y H:i', strtotime($data->cancel_at)) : null,
                'event_date' => date('d F Y', strtotime($data->project_date)),
                'status_payment' => $data->getStatusPayment(),
                'status_payment_color' => $data->getStatusPaymentColor(),
                'is_paid' => $data->isPaid(),
                'fix_price' => $finalQuotation->count() > 0 ? $finalQuotation->fix_price : $data->latestQuotation->fix_price,
                'remaining_price' => $data->getRemainingPayment(),
                'invoices' => $invoiceList,
                'general_invoice_url' => $generalInvoiceUrl
            ];

            return generalResponse(
                message: "Success",
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Adding more quotation in the selected project deal
     *
     * @param array $payload
     * @param string $projectDealUid
     * @return array
     */
    public function addMoreQuotation(array $payload, string $projectDealUid): array
    {
        DB::beginTransaction();

        try {
            $payload['quotation']['project_deal_id'] = Crypt::decryptString($projectDealUid);
            $quotation = $this->projectQuotationRepo->store(
                data: collect($payload['quotation'])->except('items')->toArray()
            );

            $quotation->items()->createMany(
                collect($payload['quotation']['items'])->map(function ($item) {
                    return [
                        'item_id' => $item,
                    ];
                })->toArray()
            );
            
            DB::commit();

            return generalResponse(
                message: __('notification.successAddQuotation')
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get design job number
     *
     * @return array
     */
    public function getDesignJob(): array
    {
        try {
            // get latest number of project
            $data = $this->projectRepo->list(
                select: 'id'
            )->count();

            $designJobNumber = $data + 1;

            return generalResponse(
                message: "Success",
                data: [
                    'designJob' => $designJobNumber
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function getProjectDealSummary(): array
    {
        return $this->generalService->getProjectDealSummary(2025);
    }

    /**
     * Cancel temporary project deal
     * 
     * @param array $payload            With this following structure
     * - string $reason
     * 
     * @return array
     */
    public function cancelProjectDeal(array $payload, string $projectDealUid): array
    {
        DB::beginTransaction();
        try {
            $projectDealId = Crypt::decryptString($projectDealUid);
            $projectDeal = $this->repo->show(uid: 'id', select: 'id,status', where: "id = {$projectDealId} and status = " . ProjectDealStatus::Temporary->value);

            if (!$projectDeal) {
                return errorResponse(message: __('notification.eventCannotBeCancel'));
            }

            $this->repo->update(data: [
                'status' => ProjectDealStatus::Canceled,
                'cancel_reason' => $payload['reason'],
                'cancel_by' => Auth::id(),
                'cancel_at' => Carbon::now()
            ], id: $projectDealId);

            ProjectDealCanceledJob::dispatch($projectDealId)->afterCommit();

            DB::commit();

            return generalResponse(
                message: __('notification.projectDealHasBeenCanceled')
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Here we request changes on final project deal
     *
     * @param array $payload                    With these following structure
     * - array $detail_changes                              With these following structure
     *      - string $old_value
     *      - string $new_value
     *      - string $label
     * @param string $projectDealUid
     * @return array
     */
    public function updateFinalDeal(array $payload, string $projectDealUid): array
    {
        DB::beginTransaction();
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $projectDealId = Crypt::decryptString($projectDealUid);

            $changes = $this->projectDealChangeRepo->store(data: [
                'requested_by' => $user->id,
                'requested_at' => \Carbon\Carbon::now(),
                'detail_changes' => $payload['detail_changes'],
                'project_deal_id' => $projectDealId,
                'status' => ProjectDealChangeStatus::Pending
            ]);

            NotifyProjectDealChangesJob::dispatch(changesId: $changes->id)->afterCommit();

            DB::commit();

            return generalResponse(
                message: "Success request changes on final event"
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Approve project deal changes
     * 
     * @param string $projectDetailChangesUid
     * 
     * @return array
     */
    public function approveChangesProjectDeal(string $projectDetailChangesUid, array $payload = []): array
    {
        DB::beginTransaction();
        try {
            $projectDetailChangesId = Crypt::decryptString($projectDetailChangesUid);

            // get detail project deal
            $change = $this->projectDealChangeRepo->show(uid: $projectDetailChangesId, relation: [
                'projectDeal:id,name,project_date',
                'projectDeal.project:id,uid,project_deal_id',
                'requester:id,email,employee_id',
                'requester.employee:id,name'
            ]);

            // return a specific message if changes has been already approved
            if ($change->status == ProjectDealChangeStatus::Approved) {
                return errorResponse(message: "Changes has already approved");
            }

            if (!empty($payload)) { // this request came from email
                $userId = $payload['approval_id'];
            } else { // this request came from website
                $user = Auth::user();
                $userId = $user->id;

                // validate permission
                if (!$user->hasPermissionTo('approve_project_deal_change')) {
                    return errorResponse(message: "You don't have permission to take this action", code: 403);
                }
            }

            // update project deal table
            $this->projectDealChangeRepo->update(
                data: [
                    'approval_at' => Carbon::now(),
                    'approval_by' => $userId,
                    'status' => ProjectDealChangeStatus::Approved
                ],
                id: $projectDetailChangesId
            );

            $changes = $change->detail_changes;
            // build payload to update the main data
            $payloadUpdate = [];
            $mainPayload = [];
            $needUpdateQuotationNote = false;
            $payloadQuotation = [];
            foreach ($changes as $key => $changeData) {
                switch ($changeData['label']) {
                    case 'Name':
                        $field = 'name';
                        break;

                    case 'Event Type':
                        $field = 'event_type';
                        break;

                    case 'Event Note':
                        $field = 'note';
                        break;

                    case 'Led Detail':
                        $field = 'led_detail';
                        break;

                    case 'Led Area':
                        $field = 'led_area';
                        break;

                    case 'Quotation Note':
                        $field = 'quotation_note';
                        break;

                    case 'Include Tax':
                        $field = 'include_tax';
                        break;

                    default:
                        $field = null;
                        break;
                }

                if ($field) {
                    if ($field == 'quotation_note') {
                        $needUpdateQuotationNote = true;
                        $payloadQuotation['description'] = $changeData['new_value'];
                    } else {
                        $payloadUpdate[$field] = $changeData['new_value'];
                        $mainPayload[$field] = $changeData['new_value'];
                    }

                }
            }

            // change the real data
            $this->repo->update(data: $payloadUpdate, id: $change->project_deal_id);

            // update quotation if needed
            if ($needUpdateQuotationNote) {
                $this->projectQuotationRepo->update(
                    data: $payloadQuotation,
                    id: 'id',
                    where: "project_deal_id = {$change->project_deal_id}"
                );
            }

            // update projects
            $this->projectRepo->update(
                data: collect($payloadUpdate)->except('include_tax')->toArray(),
                id: 'id',
                where: "project_deal_id = {$change->project_deal_id}"
            );

            // delete project cache
            if ($change->projectDeal->project) {
                (new GeneralService)->clearCache('detailProject'.$change->projectDeal->project->id);
            }

            NotifyApprovalProjectDealChangeJob::dispatch(changeId: $projectDetailChangesId, type: 'approved')->afterCommit();

            DB::commit();
            
            return generalResponse(
                message: "Success"
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Here we reject the the changes
     * 
     * @param string $projectDetailChangesUid
     * @param array $payload
     * 
     * @return array
     */
    public function rejectChangesProjectDeal(string $projectDetailChangesUid, array $payload = []): array
    {
        DB::beginTransaction();
        try {
            $projectDetailChangesId = Crypt::decryptString($projectDetailChangesUid);

            if (empty($payload)) {
                $user = Auth::user();
                $userId = $user->id;
            } else {
                $userId = $payload['approval_id'];
            }

            $this->projectDealChangeRepo->update(
                data: [
                    'status' => ProjectDealChangeStatus::Rejected,
                    'rejected_at' => Carbon::now(),
                    'rejected_by' => $userId
                ],
                id: $projectDetailChangesId
            );

            DB::commit();

            NotifyApprovalProjectDealChangeJob::dispatch(changeId: $projectDetailChangesId, type: 'rejected')->afterCommit();
            
            return generalResponse(message: "Success reject changes");
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorResponse($th);
        }
    }

    /**
     * Here we will define function to request changes in project deal fix price.
     * This changes requires approval from the management.
     *
     * @param  array  $payload  With these following structure
     *                          - string $price
     *                          - string $reason
     */
    public function requestPriceChanges(array $payload, string $projectDealUid): array
    {
        DB::beginTransaction();
        try {
            $projectDealId = Crypt::decryptString($projectDealUid);

            // validation
            // return error if current project deal already have child invoice and transactions
            $projectDeal = $this->repo->show(
                uid: $projectDealId,
                select: 'id,is_fully_paid',
                relation: [
                    'finalQuotation',
                    'invoices',
                    'transactions',
                ]
            );

            // if ($projectDeal->invoices->count() > 1 || $projectDeal->transactions->isNotEmpty()) {
            //     return errorResponse(message: __('notification.projectDealHasChildInvoicesOrTransactions'));
            // }

            // record price changes. old price came from finalQuotation->fix_price
            $changes = $this->projectDealPriceChangeRepo->store(data: [
                'project_deal_id' => $projectDealId,
                'requested_by' => Auth::id(),
                'requested_at' => Carbon::now(),
                'old_price' => $projectDeal->finalQuotation->fix_price,
                'new_price' => $payload['price'],
                'reason_id' => $payload['reason_id'],
                'custom_reason' => $payload['custom_reason'] ?? null,
                'status' => ProjectDealChangePriceStatus::Pending,
            ]);

            // notify the director to approve this changes. Send job
            NotifyRequestPriceChangesJob::dispatch(
                projectDealChangeId: $changes->id,
                newPrice: $payload['price'],
                reason: $changes->real_reason
            )->afterCommit();

            DB::commit();

            return generalResponse(message: __('notification.requestPriceChangesSuccess'));
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Approve price changes
     * Change price on quotation and raw data on invoice
     */
    public function approvePriceChanges(string $priceChangeId): array
    {
        DB::beginTransaction();
        try {
            $priceChangeId = Crypt::decryptString($priceChangeId);
            $changes = $this->projectDealPriceChangeRepo->show(uid: $priceChangeId);

            // here we will change quotation fix price and raw_data on parent invoice
            $this->projectQuotationRepo->update(
                data: [
                    'fix_price' => $changes->new_price,
                ],
                where: 'project_deal_id = '.$changes->project_deal_id,
            );

            // change raw data on invoices
            $currentInvoices = $this->invoiceRepo->list(
                select: 'id,raw_data,uid',
                where: "project_deal_id = {$changes->project_deal_id}"
            );

            foreach ($currentInvoices as $invoice) {
                $rawData = $invoice->raw_data;

                $currentFixPrice = $changes->new_price;
                $transactions = $rawData['transactions'];

                $transactionAmount = collect($transactions)->map(function ($trx) {
                    return str_replace(['Rp', ',', '.'], '', $trx['payment']);
                })->sum();
                $remaining = $currentFixPrice - $transactionAmount;

                $rawData['fixPrice'] = 'Rp'.number_format(num: $currentFixPrice);
                $rawData['remainingPayment'] = 'Rp'.number_format(num: $remaining);

                $this->invoiceRepo->update(
                    data: [
                        'raw_data' => $rawData,
                    ],
                    id: $invoice->uid
                );
            }

            // update price changes status
            // this action can take by user on the erp or from email
            // if this action came from email, we will use payload to get the user id
            if (request()->has('approvalId')) {
                $userId = request('approvalId');
            } else {
                $user = Auth::user();
                $userId = $user->id;
            }

            $this->projectDealPriceChangeRepo->update(
                data: [
                    'status' => ProjectDealChangePriceStatus::Approved,
                    'approved_at' => Carbon::now(),
                    'approved_by' => $userId,
                ],
                id: $priceChangeId
            );

            NotifyRequestPriceChangesHasBeenApproved::dispatch(changeId: $priceChangeId)->afterCommit();

            DB::commit();

            return generalResponse(
                message: __('notification.successApprovePriceChanges'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Reject price changes
     */
    public function rejectPriceChanges(string $priceChangeId, ?string $reason = null): array
    {
        DB::beginTransaction();
        try {
            $priceChangeId = Crypt::decryptString($priceChangeId);

            // this action can take by user on the erp or from email
            // if this action came from email, we will use payload to get the user id
            if (request()->has('approvalId')) {
                $userId = request('approvalId');
            } else {
                $user = Auth::user();
                $userId = $user->id;
            }

            $this->projectDealPriceChangeRepo->update(
                data: [
                    'status' => ProjectDealChangePriceStatus::Rejected->value,
                    'rejected_at' => Carbon::now(),
                    'rejected_by' => $userId,
                    'rejected_reason' => $reason ?? 'No reason provided',
                ],
                id: $priceChangeId
            );

            NotifyRequestPriceChangesHasBeenApproved::dispatch(changeId: $priceChangeId, type: 'rejected')->afterCommit();

            DB::commit();

            return generalResponse(
                message: __('notification.successRejectPriceChanges'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get price change reasons
     */
    public function getPriceChangeReasons(): array
    {
        $data = Cache::get(CacheKey::PriceChangeReasons->value);

        if (! $data) {
            $data = Cache::remember(
                key: CacheKey::PriceChangeReasons->value,
                ttl: now()->addDays(7),
                callback: function () {
                    return $this->priceChangeReasonRepo->list(
                        select: 'id,name'
                    )->map(function ($item) {
                        return [
                            'value' => $item->id,
                            'title' => $item->name,
                        ];
                    })->toArray();
                }
            );
        }

        return generalResponse(
            message: 'Success',
            data: $data
        );
    }

    /**
     * Get list of request changes on project deal price
     */
    public function requestChangesList(): array
    {
        try {
            $user = Auth::user();

            $itemsPerPage = request('itemsPerPage') ?? 10;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $where = 'id > 0';

            if (request('status')) {
                $statuses = [
                    'pending' => ProjectDealChangePriceStatus::Pending->value,
                    'approved' => ProjectDealChangePriceStatus::Approved->value,
                    'rejected' => ProjectDealChangePriceStatus::Rejected->value,
                ];

                $where .= ' and status = '.$statuses[request('status')];
            }

            $data = $this->projectDealPriceChangeRepo->pagination(
                select: 'id,project_deal_id,old_price,new_price,status,requested_by,requested_at,reason_id,custom_reason,rejected_at,approved_at',
                relation: [
                    'projectDeal:id,name,project_date',
                    'requesterBy:id,employee_id',
                    'requesterBy.employee:id,name',
                    'reason:id,name',
                ],
                page: $page,
                itemsPerPage: $itemsPerPage,
                where: $where
            );
            $totalData = $this->projectDealPriceChangeRepo->list(select: 'id', where: $where)->count();

            $output = $data->map(function ($item) {
                return [
                    'uid' => Crypt::encryptString($item->id),
                    'event_name' => $item->projectDeal->name,
                    'project_date' => date('d F Y', strtotime($item->projectDeal->project_date)),
                    'request_by' => $item->requesterBy->employee->name,
                    'old_price' => 'Rp'.number_format($item->old_price, 0, ',', '.'),
                    'new_price' => 'Rp'.number_format($item->new_price, 0, ',', '.'),
                    'reason' => $item->real_reason,
                    'approved_at' => $item->approved_at ? date('d F Y, H:i', strtotime($item->approved_at)) : null,
                    'rejected_at' => $item->rejected_at ? date('d F Y, H:i', strtotime($item->rejected_at)) : null,
                ];
            });

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
