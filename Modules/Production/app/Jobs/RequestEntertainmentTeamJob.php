<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RequestEntertainmentTeamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payload;

    public $project;

    public $entertainmentPic;

    public $requestedEmployee;

    public $employeeIds;

    /**
     * Create a new job instance.
     * @param array $payload
     * @param object $project
     * @param object $entertainmentPic
     * @param object$requestedEmployee
     * @param array $employeeIds
     */
    public function __construct(
        array $payload,
        object $project,
        object $entertainmentPic,
        object $requestedEmployee,
        array $employeeIds = [])
    {
        $this->project = $project;

        $this->payload = $payload;

        $this->employeeIds = $employeeIds;

        $this->entertainmentPic = $entertainmentPic;

        $this->requestedEmployee = $requestedEmployee;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $picEmployeeData = \Modules\Hrd\Models\Employee::selectRaw('id,line_id,telegram_chat_id,nickname')
            ->find($this->entertainmentPic->employee_id);

        $requested = \Modules\Hrd\Models\Employee::selectRaw('id,line_id,telegram_chat_id,nickname')
            ->find($this->requestedEmployee->employee_id);

        $existsEmployeeMessage = null;
        if (count($this->employeeIds) > 0) {
            $employeeData = \Modules\Hrd\Models\Employee::selectRaw('id,nickname')
                ->whereIn('id', $this->employeeIds)
                ->get();
            $employeeNames = collect((object) $employeeData)->pluck('nickname')->toArray();
            $names = implode(',', $employeeNames);

            $existsEmployeeMessage = "Halo " . $picEmployeeData->nickname . ", " . $requested->nickname . " ingin meminjam {$names} di event " . $this->project->name . " untuk tanggal " . date('d F Y', strtotime($this->project->project_date));
        }

        $defaultMessage = 'Halo ' . $picEmployeeData->nickname . ", " . $requested->nickname . " ingin meminjam tim untuk membantu pekerjaan di event " . $this->project->name . " di tanggal " . date('d F Y', strtotime($this->project->project_date)) . ". Kamu bisa memilihkan tim untuk bekerja.";


        $message = $this->payload['default_select'] ? $defaultMessage : $existsEmployeeMessage;

        $messages = [
            [
                'type' => 'text',
                'text' => $message,
            ],
            [
                'type' => 'text',
                'text' => 'Silahkan login untuk melihat detail nya'
            ]
        ];

        $telegramChatIds = [$picEmployeeData->telegram_chat_id];

        $picEmployeeData->notify(new \Modules\Production\Notifications\RequestEntertainmentTeamNotification(
            $telegramChatIds,
            $messages,
            $this->project,
        ));

        $output = formatNotifications($picEmployeeData->unreadNotifications->toArray());

        $pusher = new \App\Services\PusherNotification();

        $pusher->send('my-channel-' . $this->entertainmentPic->id, 'notification-event', $output);
    }
}
