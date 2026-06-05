<?php

namespace Modules\Production\Services;

use App\Enums\Production\ProjectDealChangeStatus;
use App\Enums\Production\ProjectDealStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Modules\Production\Models\ProjectDealChange;
use Modules\Production\Repository\ProjectDealRepository;

/**
 * MCP-facing wrapper around the final-deal change-request flow.
 *
 * It translates a friendly partial payload (e.g. {"name": "..."}) into the
 * label/old_value/new_value structure the existing approval flow expects, then
 * delegates to {@see ProjectDealService::updateFinalDeal()} WITHOUT modifying it,
 * so the website behaviour is untouched. Approval itself stays human (email/web).
 */
class ProjectDealChangeMcpService
{
    /**
     * Friendly field => change metadata.
     *
     * - label: the exact label the approval flow / email template expects.
     * - source: where the current (old) value is read from.
     * - cast: how to normalise both values for storage/rendering.
     *
     * @var array<string, array{label: string, source: string, cast: string}>
     */
    private const FIELD_MAP = [
        'name' => ['label' => 'Name', 'source' => 'deal', 'cast' => 'string'],
        'event_type' => ['label' => 'Event Type', 'source' => 'deal', 'cast' => 'string'],
        'note' => ['label' => 'Event Note', 'source' => 'deal', 'cast' => 'string'],
        'led_area' => ['label' => 'Led Area', 'source' => 'deal', 'cast' => 'string'],
        'led_detail' => ['label' => 'Led Detail', 'source' => 'deal', 'cast' => 'array'],
        'quotation_note' => ['label' => 'Quotation Note', 'source' => 'quotation', 'cast' => 'string'],
        'include_tax' => ['label' => 'Include Tax', 'source' => 'deal', 'cast' => 'boolean'],
        'with_accommodation' => ['label' => 'With Accommodation', 'source' => 'quotation', 'cast' => 'boolean'],
    ];

    public function __construct(
        private readonly ProjectDealService $projectDealService,
        private readonly ProjectDealRepository $projectDealRepo,
    ) {}

    /**
     * Submit a change request for a FINAL project deal from a friendly partial payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function requestFinalDealChange(array $payload, string $projectDealUid): array
    {
        try {
            $projectDealId = Crypt::decryptString($projectDealUid);

            $deal = $this->projectDealRepo->show(
                uid: (string) $projectDealId,
                select: 'id,name,event_type,note,led_area,led_detail,include_tax,status',
                relation: [
                    'latestQuotation:id,project_deal_id,description,is_include_accomodation',
                ]
            );

            if (! $deal) {
                return errorResponse(__('notification.dataNotFound'));
            }

            if ($deal->status !== ProjectDealStatus::Final) {
                return errorResponse(__('notification.finalDealNotFinal'));
            }

            $detailChanges = $this->buildDetailChanges($payload, $deal);

            if (empty($detailChanges)) {
                return errorResponse(__('notification.noFinalDealChangeProvided'));
            }

            // Delegate to the untouched main service (creates the pending request + notifies approvers).
            $result = $this->projectDealService->updateFinalDeal(
                payload: ['detail_changes' => $detailChanges],
                projectDealUid: $projectDealUid,
            );

            if ($result['error']) {
                return $result;
            }

            $change = ProjectDealChange::query()
                ->where('project_deal_id', $projectDealId)
                ->where('requested_by', Auth::id())
                ->latest('id')
                ->first();

            return generalResponse(
                message: __('notification.finalDealChangeSubmitted'),
                data: [
                    'status' => 'pending',
                    'requires_approval' => true,
                    'change_uid' => $change ? Crypt::encryptString($change->id) : null,
                    'requested_changes' => collect($detailChanges)->map(fn ($item) => [
                        'label' => $item['label'],
                        'old_value' => $item['old_value'],
                        'new_value' => $item['new_value'],
                    ])->values()->all(),
                    'note' => 'This change takes effect only after a human approver approves it via email/web.',
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * List change requests (and approval status) for a project deal.
     *
     * @return array<string, mixed>
     */
    public function listFinalDealChanges(string $projectDealUid): array
    {
        try {
            $projectDealId = Crypt::decryptString($projectDealUid);

            $statusFilter = $this->resolveStatusFilter(request('status'));

            $changes = ProjectDealChange::query()
                ->where('project_deal_id', $projectDealId)
                ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter->value))
                ->with([
                    'requester:id,employee_id',
                    'requester.employee:id,nickname',
                    'approval:id,employee_id',
                    'approval.employee:id,nickname',
                ])
                ->orderByDesc('id')
                ->get();

            $mapped = $changes->map(fn (ProjectDealChange $change) => [
                'change_uid' => Crypt::encryptString($change->id),
                'status' => $this->statusLabel($change->status),
                'requested_by' => $change->requester?->employee?->nickname ?? '-',
                'requested_at' => $this->formatDate($change->requested_at),
                'approved_by' => $change->approval?->employee?->nickname,
                'approved_at' => $this->formatDate($change->approval_at),
                'rejected_at' => $this->formatDate($change->rejected_at),
                'detail_changes' => $change->detail_changes,
            ])->values();

            return generalResponse(
                message: 'Success',
                data: [
                    'project_deal_uid' => $projectDealUid,
                    'pending_count' => $changes->where('status', ProjectDealChangeStatus::Pending)->count(),
                    'changes' => $mapped->all(),
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Translate provided friendly fields into label/old_value/new_value items.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, old_value: mixed, new_value: mixed}>
     */
    private function buildDetailChanges(array $payload, object $deal): array
    {
        $items = [];

        foreach (self::FIELD_MAP as $field => $meta) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null) {
                continue;
            }

            $items[] = [
                'label' => $meta['label'],
                'old_value' => $this->castValue($this->currentValue($field, $meta, $deal), $meta['cast']),
                'new_value' => $this->castValue($payload[$field], $meta['cast']),
            ];
        }

        return $items;
    }

    /**
     * Read the current (old) value for a field from the deal or its latest quotation.
     */
    private function currentValue(string $field, array $meta, object $deal): mixed
    {
        if ($meta['source'] === 'quotation') {
            $quotation = $deal->latestQuotation;

            return match ($field) {
                'quotation_note' => $quotation?->description,
                'with_accommodation' => $quotation?->is_include_accomodation,
                default => null,
            };
        }

        return match ($field) {
            'event_type' => $deal->event_type?->value,
            default => $deal->{$field} ?? null,
        };
    }

    private function castValue(mixed $value, string $cast): mixed
    {
        return match ($cast) {
            'boolean' => (bool) $value,
            'array' => is_array($value) ? $value : ($value === null ? [] : [$value]),
            default => $value === null ? '' : (string) $value,
        };
    }

    private function formatDate(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateTimeString() : null;
    }

    private function statusLabel(ProjectDealChangeStatus $status): string
    {
        return match ($status) {
            ProjectDealChangeStatus::Pending => 'pending',
            ProjectDealChangeStatus::Approved => 'approved',
            ProjectDealChangeStatus::Rejected => 'rejected',
        };
    }

    private function resolveStatusFilter(?string $status): ?ProjectDealChangeStatus
    {
        return match ($status) {
            'pending' => ProjectDealChangeStatus::Pending,
            'approved' => ProjectDealChangeStatus::Approved,
            'rejected' => ProjectDealChangeStatus::Rejected,
            default => null,
        };
    }
}
