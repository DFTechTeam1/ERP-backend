<?php

namespace Modules\Finance\Jobs;

use App\Enums\Production\ProjectDealStatus;
use App\Services\PusherNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Finance\Notifications\ProjectHasBeenFinal as NotificationsProjectHasBeenFinal;
use Modules\Production\Repository\ProjectDealRepository;

class ProjectHasBeenFinal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $projectDealId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $projectDealId)
    {
        $this->projectDealId = $projectDealId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $repo = new ProjectDealRepository;

        $projectDeal = $repo->show(uid: $this->projectDealId, select: 'id,name,project_date,published_at,published_by,status', relation: [
            'publishedBy:id,email,employee_id',
            'publishedBy.employee:id,nickname',
            'finalQuotation'
        ]);

        $users = \App\Models\User::role(['finance', 'root'])->get();

        $pusher = new PusherNotification;

        foreach ($users as $user) {
            $user->notify(new NotificationsProjectHasBeenFinal(
                projectDeal: $projectDeal,
            ));

            // send pusher notification
            $pusher->send(channel: "my-channel-{$user->id}", event: 'notification-event', payload: [
                'type' => 'finance',
            ]);
        }

        $developers = \App\Models\User::where('email', config('app.developer_email'))->first();

        // only send notification to developers
        if ($developers) {
            $developers->notify(new NotificationsProjectHasBeenFinal(
                projectDeal: $projectDeal,
                actorName: $projectDeal->status == ProjectDealStatus::Final ? $projectDeal->publishedBy?->employee?->nickname : null,
                publishedAt: $projectDeal->status == ProjectDealStatus::Final ? date('d F Y H:i', strtotime($projectDeal->published_at)) : null,
                finalPrice: $projectDeal->finalQuotation ? "Rp" . number_format($projectDeal->finalQuotation->fix_price, 0, ',', '.') : null,
                eventName: $projectDeal->status == ProjectDealStatus::Final ? $projectDeal->name : null,
                eventDate: $projectDeal->status == ProjectDealStatus::Final ? date('d F Y', strtotime($projectDeal->project_date)) : null,
            ));
        }
    }
}
