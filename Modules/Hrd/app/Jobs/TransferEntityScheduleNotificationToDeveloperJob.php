<?php

namespace Modules\Hrd\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Email\Data\Notification\SendSlackMessageData;
use Modules\Email\Data\Notification\SlackSectionBlockData;
use Modules\Email\Notifications\GlobalSlackNotification;
use Modules\Hrd\Data\TransferHistory\HistoryData;

class TransferEntityScheduleNotificationToDeveloperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public HistoryData $historyData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $developer = \App\Models\User::where('email', config('app.developer_email'))->first();

        if ($developer) {
            $message = "";
            foreach ($this->historyData->validData as $validEmployee => $validData) {
                $reason = collect($validData)->pluck('reason')->implode(',');
                $message .= "{$validEmployee}: {$reason}\n";
            }
            foreach ($this->historyData->failedData as $failedEmployee => $failedData) {
                $reason = collect($failedData)->pluck('reason')->implode(', ');
                $message .= "{$failedEmployee}: {$reason}\n";
            }
            $payloadData = SendSlackMessageData::from([
                'messageTitle' => 'Title',
                'title' => "Report of Employee Transfer Schedule",
                'sectionBlock' => [
                    new SlackSectionBlockData(
                        message: "All employee transfer schedule for " . now()->toDayDateTimeString() . " has been running successfully\nHere are the result",
                        type: null
                    ),
                    new SlackSectionBlockData(
                        message: $message,
                        type: null
                    ),
                ],
                'contextBlock' => null
            ]);
            $developer->notify(new GlobalSlackNotification($payloadData));
        }
    }
}
