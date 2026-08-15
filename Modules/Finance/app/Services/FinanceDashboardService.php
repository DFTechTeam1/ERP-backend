<?php

namespace Modules\Finance\Services;

use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\Models\InvoiceRequestUpdate;
use Modules\Finance\Models\ProjectDealPriceChange;

/**
 * Read-only queues surfaced on the Finance dashboard.
 *
 * FinanceInsightService already returns the aggregate insight bundle
 * (overview, receivables aging, refunds, top deals, payment status).
 * This service adds the two "things awaiting your action" lists:
 *
 *   - Pending invoice edit requests (invoice_request_updates)
 *   - Pending price-change requests (project_deal_price_changes)
 *
 * Both are read-only on the dashboard - clicking a row deep-links into
 * the existing approval flow.
 */
class FinanceDashboardService
{
    /**
     * Roles allowed to see the Finance dashboard queues.
     * Director/Root are included so exec can preview the finance workload.
     */
    private const ALLOWED_ROLES = [
        BaseRole::Finance,
        BaseRole::Director,
        BaseRole::Root,
    ];

    /**
     * List of pending invoice edit requests, newest first.
     *
     * @return array{error:bool, message:string, data:array{items:array<int, array<string,mixed>>, total:int}, code:int}
     */
    public function getPendingInvoiceUpdates(int $limit = 10): array
    {
        try {
            if (! $this->authorizedUser()) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $limit = max(1, min($limit, 50));

            $query = InvoiceRequestUpdate::query()
                ->with([
                    'invoice:id,uid,number,amount,project_deal_id',
                    'invoice.projectDeal:id,name,identifier_number',
                    'user:id,employee_id',
                    'user.employee:id,name',
                ])
                ->where('status', InvoiceRequestUpdateStatus::Pending->value)
                ->latest('id');

            $total = (clone $query)->count();

            $items = $query
                ->limit($limit)
                ->get()
                ->map(function (InvoiceRequestUpdate $row) {
                    $invoice = $row->invoice;
                    $deal = optional($invoice)->projectDeal;

                    return [
                        'id' => (int) $row->id,
                        'invoice_uid' => optional($invoice)->uid,
                        'invoice_number' => optional($invoice)->number,
                        'project_name' => optional($deal)->name ?? '-',
                        'identifier_number' => optional($deal)->identifier_number,
                        'requested_amount' => (float) $row->amount,
                        'requested_payment_date' => $row->payment_date
                            ? (string) $row->payment_date
                            : null,
                        'requested_by' => optional(optional($row->user)->employee)->name ?? '-',
                        'requested_at' => optional($row->created_at)->toDateTimeString(),
                        'reason' => $row->reason,
                    ];
                })
                ->all();

            return generalResponse(
                message: 'Success',
                data: ['items' => $items, 'total' => $total],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * List of pending price-change requests, newest first.
     *
     * @return array{error:bool, message:string, data:array{items:array<int, array<string,mixed>>, total:int}, code:int}
     */
    public function getPendingPriceChanges(int $limit = 10): array
    {
        try {
            if (! $this->authorizedUser()) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $limit = max(1, min($limit, 50));

            $query = ProjectDealPriceChange::query()
                ->with([
                    'projectDeal:id,name,identifier_number',
                    'reason:id,name',
                    'requesterBy:id,employee_id',
                    'requesterBy.employee:id,name',
                ])
                ->where('status', ProjectDealChangePriceStatus::Pending->value)
                ->latest('id');

            $total = (clone $query)->count();

            $items = $query
                ->limit($limit)
                ->get()
                ->map(function (ProjectDealPriceChange $row) {
                    $deal = $row->projectDeal;
                    $delta = (float) $row->new_price - (float) $row->old_price;

                    return [
                        'id' => (int) $row->id,
                        'project_name' => optional($deal)->name ?? '-',
                        'identifier_number' => optional($deal)->identifier_number,
                        'old_price' => (float) $row->old_price,
                        'new_price' => (float) $row->new_price,
                        'delta' => $delta,
                        'reason' => $row->real_reason,
                        'requested_by' => optional(optional($row->requesterBy)->employee)->name ?? '-',
                        'requested_at' => $row->requested_at
                            ? (string) $row->requested_at
                            : optional($row->created_at)->toDateTimeString(),
                    ];
                })
                ->all();

            return generalResponse(
                message: 'Success',
                data: ['items' => $items, 'total' => $total],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    private function authorizedUser(): ?User
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $allowed = array_map(fn (BaseRole $r) => $r->value, self::ALLOWED_ROLES);

        return $user->hasRole($allowed) ? $user : null;
    }
}
