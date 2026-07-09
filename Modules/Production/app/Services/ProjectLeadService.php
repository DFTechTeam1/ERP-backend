<?php

namespace Modules\Production\Services;

use App\Data\Production\Lead\CancelLeadData;
use App\Enums\Production\ProjectLeadStatus;
use App\Exceptions\DataNotFound;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Modules\Production\Exceptions\ProjectLeadAlreadyHaveRelation;
use Modules\Production\Exceptions\ProjectLeadHaveBeenPast;
use Modules\Production\Repository\ProjectLeadRepository;

class ProjectLeadService {
    public function __construct(
        private readonly ProjectLeadRepository $projectLeadRepo
    ) {}

    /**
     * Cancel project lead data, then reset the PIC queue
     *
     * @param CancelLeadData $payload
     * @param string $projectLeadUid
     * @return array
     */
    public function cancel(CancelLeadData $payload, string $projectLeadUid): array
    {
        try {
            $lead = $this->projectLeadRepo->show(
                uid: $projectLeadUid,
                select: 'id,name,project_date,project_deal_id',
                relation: [
                    'projectDeal:id'
                ]
            );

            if (! $lead) {
                throw new DataNotFound(__('notification.projectLeadNotFound'));
            }

            if ($lead->projectLead) {
                throw new ProjectLeadAlreadyHaveRelation();
            }

            if (Carbon::parse($lead->project_date)->timezone(config('app.timezone'))->isPast()) {
                throw new ProjectLeadHaveBeenPast();
            }

            $this->projectLeadRepo->update(
                data: [
                    'status' => ProjectLeadStatus::CANCELLED,
                    'cancel_reason' => $payload->reason,
                    'cancel_at' => now(),
                    'cancel_by' => Auth::id(),
                    'skip_check' => 1
                ],
                id: $projectLeadUid
            );

            $queue = Http::withToken(request()->bearerToken())
                ->get(config('app.python_endpoint') . "/pic-assignment/queue/{$projectLeadUid}")
                ->json();

            logging('QUEUE RESULT AFTER CANCEL', [
                'queue' => $queue
            ]);

            return generalResponse(message: __('notification.projectLeadHasBeenCanclled'));
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}