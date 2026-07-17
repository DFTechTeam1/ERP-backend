<?php

namespace Modules\Production\Services;

use App\Data\Production\Lead\CancelLeadData;
use App\Enums\Production\ProjectLeadStatus;
use App\Exceptions\DataNotFound;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Modules\Company\Jobs\SlackNotificationJob;
use Modules\Production\Exceptions\ProjectLeadAlreadyCancel;
use Modules\Production\Exceptions\ProjectLeadAlreadyHaveRelation;
use Modules\Production\Exceptions\ProjectLeadHaveBeenPast;
use Modules\Production\Models\ProjectLead;
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
                select: 'id,name,project_date,project_deal_id,status',
                relation: [
                    'projectDeal:id'
                ]
            );

            if ($lead->status == ProjectLeadStatus::CANCELLED) {
                throw new ProjectLeadAlreadyCancel();
            }

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
                ->get(config('app.python_endpoint') . "/pic-assignment/queue/{$projectLeadUid}");

            $this->cancelNotify($queue, $lead, null);

            return generalResponse(message: __('notification.projectLeadHasBeenCanclled'));
        } catch (\Throwable $th) {
            $this->cancelNotify(
                null,
                null,
                errorMessage($th)
            );
            return errorResponse($th);
        }
    }

    /**
     * Notify cancel project action
     *
     * @param \Illuminate\Http\Client\Response|null $queueResponse
     * @param \Illuminate\Database\Eloquent\Collection|null $lead
     * @param string|null $errorMessage
     * @return void
     */
    protected function cancelNotify(
        \Illuminate\Http\Client\Response|null $queueResponse,
        \Illuminate\Database\Eloquent\Collection|ProjectLead|null $lead,
        string|null $errorMessage
    ): void
    {
        if ($lead) {
            $message = $queueResponse->ok() ? "Project lead {$lead->name} cancel & queue successfully." : "Cancel action for {$lead->name} failed";
            $title = $queueResponse->ok() ? "Lead cancel success" : 'Lead cancel failed';
        } else if (! $queueResponse && ! $lead) {
            $message = $errorMessage;
            $title = "Error while cancel project deal";
        } else {
            $message = "Error while cancel project deal";
            $title = "Error while cancel project deal";
        }

        SlackNotificationJob::dispatch(
            previewMessage: $title,
            message: $message,
            blockHeader: $title,
        );
    }
}