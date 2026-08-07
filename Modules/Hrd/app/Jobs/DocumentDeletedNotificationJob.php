<?php

namespace Modules\Hrd\Jobs;

use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Modules\Hrd\Notifications\DocumentDeleted;
use Modules\Hrd\Repository\EmployeeRepository;

class DocumentDeletedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array<int, int>  $employeeIds  Owners of the deleted documents to notify
     */
    public function __construct(
        private readonly array $employeeIds
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employees = app(EmployeeRepository::class)->list(
            select: 'id,name,email,position_id',
            whereIn: [
                'key' => 'id',
                'value' => $this->employeeIds,
            ],
            relation: [
                'position:id,division_id',
            ]
        );

        $service = app(RealtimeNotificationService::class);

        foreach ($employees as $employee) {
            $service->send(
                recipients: $employee,
                topic: RealtimeNotificationService::TOPIC_GENERAL,
                payload: [
                    'title' => __('notification.documentDeletedTitle'),
                    'message' => __('notification.documentDeletedMessage'),
                    'icon' => '🗑️',
                    'action' => 'document_deleted',
                ],
            );

            if ($employee->email) {
                Notification::send($employee, new DocumentDeleted($employee->name));
            }
        }
    }
}
