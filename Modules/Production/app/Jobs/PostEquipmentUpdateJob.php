<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PostEquipmentUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $projectId;

    public array $requestedData;

    public object $project;

    public bool $userCanAcceptRequest;

    public array $projectManagers;

    /**
     * Create a new job instance.
     */
    public function __construct(string $projectId, array $requestedData, bool $userCanAcceptRequest)
    {
        $this->projectId = $projectId;

        $this->requestedData = $requestedData;

        $this->userCanAcceptRequest = $userCanAcceptRequest;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = auth()->user();
        $statuses = \App\Enums\Production\RequestEquipmentStatus::cases();

        $this->project = \Modules\Production\Models\Project::with([
                'personInCharges:id,project_id,pic_id',
                'personInCharges.employee:id,name,line_id,user_id'
            ])
            ->where('uid', $this->projectId)
            ->first();

        $this->projectManagers = collect($this->project->personInCharges)->filter(function ($filter) {
                return $filter->employee->user_id;
            })->map(function ($item) {
                return [
                    'line_id' => $item->employee->line_id,
                    'name' => $item->employee->name,
                    'id' => $item->employee->id,
                    'user_id' => $item->employee->user_id,
                ];
            })->toArray();

        logging('project: ', [$this->project]);

        /**
         * If all requested status is ready, then notify user if equipment is ready
         * Otherwise tell user to check updated equipment status from the apps
         * Also check the permission first
         */
        $unique = array_unique(collect($this->requestedData['items'])->pluck('status')->toArray());

        if (count($unique) == 1 && $unique[0] == \App\Enums\Production\RequestEquipmentStatus::Ready->value && $this->userCanAcceptRequest) {
            $this->sendEquipmentReadyNotification();
        } else {
            $this->sendUpdatedStatusNotification();
        }
    }

    /**
     * Send notification to employee (Project Manager)
     *
     * @return void
     */
    protected function sendEquipmentReadyNotification()
    {
        logging('project managers: ', $this->projectManagers);

        $userIds = collect($this->projectManagers)->pluck('user_id')->toArray();

        logging('selected user id: ', $userIds);

        $users = \App\Models\User::whereIn('id', $userIds)->get();

        logging('send to users: ', $users->toArray());

        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo, permintaan equipment kamu untuk project ' . $this->project->name . ' sudah disiapkan dan sudah bisa di ambil ya',
            ],
        ];

        \Illuminate\Support\Facades\Notification::send($users, new \Modules\Production\Notifications\RequestEquipmentNotification($this->projectManagers, $messages));
    }

    protected function sendUpdatedStatusNotification()
    {
        if ($this->userCanAcceptRequest) {
            // send to inventaris
            $employees = getPicOfInventory();
        } else {
            // send to project manager
            $employees = $this->projectManagers;
        }

        $userIds = collect($employees)->pluck('user_id')->toArray();

        $users = \App\Models\User::whereIn('id', $userIds)->get();

        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo, ada perubahan status nih untuk request equipment di event ' . $this->project->name
            ],
            [
                'type' => 'text',
                'text' => 'Klik link dibawah untuk melihat detail nya'
            ],
            [
                'type' => 'text',
                'text' => config('app.frontend_url') . '/auth/a/login?redirect=/admin/production/project/' . $this->projectId,
            ],
        ];

        \Illuminate\Support\Facades\Notification::send($users, new \Modules\Production\Notifications\RequestEquipmentNotification($employees, $messages));
    }
}
