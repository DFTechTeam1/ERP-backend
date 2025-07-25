<?php

namespace Modules\Production\Services;

use App\Actions\CopyDealToProject;
use App\Actions\CreateQuotation;
use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\ProjectStatus;
use App\Enums\Transaction\InvoiceStatus;
use App\Enums\Transaction\TransactionType;
use App\Services\EncryptionService;
use App\Services\GeneralService;
use App\Services\Geocoding;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Jobs\ProjectHasBeenFinal;
use Modules\Production\Repository\ProjectDealMarketingRepository;
use Modules\Production\Repository\ProjectDealRepository;
use Modules\Production\Repository\ProjectQuotationRepository;
use Modules\Production\Repository\ProjectRepository;

class ProjectDealService
{
    private $repo;

    private $marketingRepo;

    private $generalService;

    private $projectQuotationRepo;

    private $projectRepo;

    private $geocoding;

    /**
     * Construction Data
     */
    public function __construct(
        ProjectDealRepository $repo,
        ProjectDealMarketingRepository $marketingRepo,
        GeneralService $generalService,
        ProjectQuotationRepository $projectQuotationRepo,
        ProjectRepository $projectRepo,
        Geocoding $geocoding
    ) {
        $this->repo = $repo;

        $this->marketingRepo = $marketingRepo;

        $this->generalService = $generalService;

        $this->projectQuotationRepo = $projectQuotationRepo;

        $this->projectRepo = $projectRepo;

        $this->geocoding = $geocoding;
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
            $itemsPerPage = request('itemsPerPage') ?? 2;
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
            $totalData = $this->repo->list('id', $where)->count();

            $paginated = $paginated->map(function ($item) {

                $marketing = implode(',', $item->marketings->pluck('employee.nickname')->toArray());

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
                    'status_project' => $item->status->label(),
                    'status_project_color' => $item->status->color(),
                    'fix_price' => $item->getFinalPrice(formatPrice: true),
                    'latest_price' => $item->getLatestPrice(formatPrice: true),
                    'is_fully_paid' => (bool) $item->is_fully_paid,
                    'status_payment' => $item->getStatusPayment(),
                    'status_payment_color' => $item->getStatusPaymentColor(),
                    'can_make_payment' => $item->canMakePayment(),
                    'can_publish_project' => $item->canPublishProject(),
                    'can_make_final' => $item->canMakeFinal(),
                    'can_edit' => !$item->isFinal(),
                    'can_delete' => (bool) !$item->isFinal(),
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
                    })
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
                'quotations.items'
            ]);

            // only not finalized project that can be deleted
            if ($detail->isFinal()) return errorResponse('Cannot delete finalized project');

            foreach ($detail->quotations as $quotation) {
                $quotation->items()->delete();
            }

            $detail->quotations()->delete();
            $detail->marketings()->delete();

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
            select: 'id,name,project_date,customer_id,event_type,venue,collaboration,note,led_area,led_detail,country_id,state_id,city_id,project_class_id,is_high_season,equipment_type',
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
                select: "id,name,project_date,customer_id,event_type,venue,collaboration,project_class_id,city_id,note,led_detail,is_fully_paid,status",
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
            $invoiceList = $data->invoices->map(function ($invoice) use ($projectDealUid, $user) {
                $invoiceUrl = \Illuminate\Support\Facades\URL::signedRoute(
                    name: 'invoice.download',
                    parameters: [
                        'i' => $projectDealUid,
                        'n' => $invoice->uid
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
                'is_final' => $data->isFinal(),
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
}
