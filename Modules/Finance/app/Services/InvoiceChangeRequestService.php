<?php

namespace Modules\Finance\Services;

use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Models\User;
use App\Services\GeneralService;
use App\Services\RealtimeNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Models\InvoiceRequestUpdate;
use Modules\Hrd\Models\Employee;

/**
 * List + approve + reject for the "Invoice Changes" menu under Request.
 *
 * Approve/reject delegate to InvoiceService (which owns the business
 * logic + email jobs). This service adds:
 *   - Paginated list with status tabs + statusCount for badges
 *   - Realtime bell notification when a request is created, approved, or
 *     rejected (via RealtimeNotificationService)
 */
class InvoiceChangeRequestService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly RealtimeNotificationService $realtime,
        private readonly GeneralService $general,
    ) {}

    /**
     * Paginated list of invoice change requests, filterable by status tab.
     *
     * @return array{error:bool, message:string, data:array{paginated:array<int, array<string,mixed>>, totalData:int, statusCount:array<string,int>}, code:int}
     */
    public function list(?string $status = null, int $page = 1, int $itemsPerPage = 10): array
    {
        try {
            $itemsPerPage = max(1, min($itemsPerPage, 100));
            $page = max(1, $page);

            $statusValue = match ($status) {
                'pending' => InvoiceRequestUpdateStatus::Pending->value,
                'approved' => InvoiceRequestUpdateStatus::Approved->value,
                'rejected' => InvoiceRequestUpdateStatus::Rejected->value,
                default => null,
            };

            $query = InvoiceRequestUpdate::query()
                ->with([
                    'invoice:id,uid,number,amount,payment_date,project_deal_id',
                    'invoice.projectDeal:id,name,project_date,identifier_number',
                    'user:id,employee_id',
                    'user.employee:id,name',
                ])
                ->whereHas('invoice.projectDeal')
                ->latest('id');

            if ($statusValue !== null) {
                $query->where('status', $statusValue);
            }

            $paginator = $query->paginate(perPage: $itemsPerPage, page: $page);

            $paginated = $paginator->getCollection()
                ->map(fn (InvoiceRequestUpdate $row) => $this->transformRow($row))
                ->values()
                ->all();

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $paginated,
                    'totalData' => $paginator->total(),
                    'statusCount' => $this->statusCount(),
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Approve a pending request. Delegates the business logic to
     * InvoiceService::approveChanges and then fires a realtime bell
     * notification to the original requester.
     */
    public function approve(int $id): array
    {
        $row = InvoiceRequestUpdate::query()
            ->with([
                'invoice:id,uid,project_deal_id',
                'invoice.projectDeal:id,name,identifier_number',
                'user:id',
            ])
            ->find($id);

        if (! $row) {
            return errorResponse(__('global.notFound'), code: 404);
        }
        if ($row->status !== InvoiceRequestUpdateStatus::Pending) {
            return errorResponse(__('notification.noChangesToApprove'), code: 422);
        }

        $result = $this->invoiceService->approveChanges(
            invoiceUid: $row->invoice->uid,
            fromExternalUrl: false,
            pendingUpdateId: $row->id,
        );

        if (! ($result['error'] ?? false)) {
            $this->notifyRequesterOfDecision($row, approved: true);
        }

        return $result;
    }

    /**
     * Reject a pending request. Delegates to InvoiceService::rejectChanges.
     */
    public function reject(int $id, ?string $reason = null): array
    {
        $row = InvoiceRequestUpdate::query()
            ->with([
                'invoice:id,uid,project_deal_id',
                'invoice.projectDeal:id,name,identifier_number',
                'user:id',
            ])
            ->find($id);

        if (! $row) {
            return errorResponse(__('global.notFound'), code: 404);
        }
        if ($row->status !== InvoiceRequestUpdateStatus::Pending) {
            return errorResponse(__('notification.noChangesToApprove'), code: 422);
        }

        $result = $this->invoiceService->rejectChanges(
            payload: ['reason' => $reason ?? ''],
            invoiceUid: $row->invoice->uid,
            fromExternalUrl: false,
            pendingUpdateId: $row->id,
        );

        if (! ($result['error'] ?? false)) {
            $this->notifyRequesterOfDecision($row, approved: false, reason: $reason);
        }

        return $result;
    }

    /**
     * @return array<string,int> Counts per status key used by the tab badges.
     */
    private function statusCount(): array
    {
        $counts = InvoiceRequestUpdate::query()
            ->whereHas('invoice.projectDeal')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'pending' => (int) ($counts[InvoiceRequestUpdateStatus::Pending->value] ?? 0),
            'approved' => (int) ($counts[InvoiceRequestUpdateStatus::Approved->value] ?? 0),
            'rejected' => (int) ($counts[InvoiceRequestUpdateStatus::Rejected->value] ?? 0),
            'all' => array_sum(array_map('intval', $counts)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function transformRow(InvoiceRequestUpdate $row): array
    {
        $invoice = $row->invoice;
        $deal = optional($invoice)->projectDeal;
        $requester = optional(optional($row->user)->employee)->name ?? '-';

        return [
            'id' => (int) $row->id,
            'uid' => Crypt::encryptString((string) $row->id),
            'event_name' => optional($deal)->name ?? '-',
            'project_deal_uid' => $deal ? Crypt::encryptString((string) $deal->id) : null,
            'identifier_number' => optional($deal)->identifier_number,
            'project_date' => optional(optional($deal)->project_date)?->toDateString(),
            'invoice_number' => optional($invoice)->number,
            'invoice_current_amount' => (float) (optional($invoice)->amount ?? 0),
            'invoice_current_payment_date' => optional($invoice)->payment_date
                ? (string) $invoice->payment_date
                : null,
            'requested_amount' => (float) $row->amount,
            'requested_payment_date' => $row->payment_date ? (string) $row->payment_date : null,
            'request_by' => $requester,
            'requested_at' => optional($row->created_at)->toDateTimeString(),
            'approved_at' => optional($row->approved_at)?->toDateTimeString(),
            'rejected_at' => optional($row->rejected_at)?->toDateTimeString(),
            'reason' => $row->reason,
            'status' => strtolower($row->status->name),
            'can_approve' => $row->status === InvoiceRequestUpdateStatus::Pending,
            'can_reject' => $row->status === InvoiceRequestUpdateStatus::Pending,
        ];
    }

    /**
     * Ping the original requester's bell so they see the outcome live.
     */
    private function notifyRequesterOfDecision(
        InvoiceRequestUpdate $row,
        bool $approved,
        ?string $reason = null,
    ): void {
        $requester = $row->user;
        if (! $requester instanceof User) {
            return;
        }

        $dealName = optional(optional($row->invoice)->projectDeal)->name ?? '-';
        $invoiceNo = optional($row->invoice)->number ?? '-';
        $verb = $approved ? __('notification.invoiceChangeApproved') : __('notification.invoiceChangeRejected');
        $body = $approved
            ? __('notification.invoiceChangeApprovedBody', ['invoice' => $invoiceNo, 'deal' => $dealName])
            : __('notification.invoiceChangeRejectedBody', ['invoice' => $invoiceNo, 'deal' => $dealName, 'reason' => $reason ?: '-']);

        $this->realtime->send(
            recipients: $requester,
            topic: RealtimeNotificationService::TOPIC_GENERAL,
            payload: [
                'title' => $verb,
                'message' => $body,
                'icon' => $approved ? '✅' : '❌',
                'url' => '/admin/finance/invoice-changes',
                'action' => $approved ? 'invoice_changes_approved' : 'invoice_changes_rejected',
                'data' => ['invoice_request_update_id' => $row->id],
            ],
        );
    }

    /**
     * Ping every configured approver's bell when a new request is created.
     * Called from InvoiceService::updateTemporaryData.
     */
    public function notifyApproversOfNewRequest(InvoiceRequestUpdate $row): void
    {
        $personsSetting = $this->general->getSettingByKey('person_to_approve_invoice_changes');
        if (! $personsSetting) {
            return;
        }

        $uids = json_decode($personsSetting, true);
        if (! is_array($uids) || empty($uids)) {
            return;
        }

        $employees = Employee::with('user')
            ->whereIn('uid', $uids)
            ->get()
            ->filter(fn (Employee $e) => $e->user !== null);

        if ($employees->isEmpty()) {
            return;
        }

        $deal = optional(optional($row->invoice)->projectDeal);
        $requesterName = optional(optional($row->user)->employee)->name ?? '-';

        $this->realtime->send(
            recipients: $employees->map(fn (Employee $e) => $e->user)->values(),
            topic: RealtimeNotificationService::TOPIC_GENERAL,
            payload: [
                'title' => __('notification.invoiceChangeRequested'),
                'message' => __('notification.invoiceChangeRequestedBody', [
                    'invoice' => optional($row->invoice)->number ?? '-',
                    'deal' => $deal->name ?? '-',
                    'requester' => $requesterName,
                ]),
                'icon' => '📝',
                'url' => '/admin/finance/invoice-changes',
                'action' => 'invoice_changes_requested',
                'data' => ['invoice_request_update_id' => $row->id],
            ],
        );
    }
}
