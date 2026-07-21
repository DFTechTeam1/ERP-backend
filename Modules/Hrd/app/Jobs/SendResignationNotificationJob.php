<?php

namespace Modules\Hrd\Jobs;

use App\Enums\System\BaseRole;
use App\Models\User;
use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Notifications\EmployeeResignationNotification;

/**
 * Notify the resigning employee, their boss (if any) and the whole HR team about a
 * processed resignation, both via email and via realtime in-app bell notification.
 */
class SendResignationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $employeeId,
        private readonly string $resignDate,
        private readonly ?string $remark = null,
    ) {}

    public function handle(): void
    {
        $employee = Employee::with(['boss:id,name,email,user_id', 'user:id,employee_id'])
            ->select('id', 'name', 'email', 'boss_id', 'user_id')
            ->find($this->employeeId);

        if (! $employee) {
            return;
        }

        $hrUsers = User::role(BaseRole::Hrd->value)->get();

        $recipients = collect([$employee]);

        if ($employee->boss) {
            $recipients->push($employee->boss);
        }

        $recipients = $recipients->merge($hrUsers);

        $this->sendRealtime($recipients, $employee->name);
        $this->sendEmail($recipients->filter(fn ($recipient) => ! empty($recipient->email)), $employee->name);
    }

    private function sendRealtime(Collection $recipients, string $employeeName): void
    {
        app(RealtimeNotificationService::class)->send(
            recipients: $recipients,
            topic: RealtimeNotificationService::TOPIC_GENERAL,
            payload: [
                'title' => __('notification.resignationInAppTitle'),
                'message' => __('notification.resignationInAppMessage', [
                    'name' => $employeeName,
                    'date' => date('d F Y', strtotime($this->resignDate)),
                ]),
                'icon' => '👋',
                'action' => 'employee_resigned',
                'data' => [
                    'employee_id' => $this->employeeId,
                    'resign_date' => $this->resignDate,
                ],
            ],
        );
    }

    private function sendEmail(Collection $recipients, string $employeeName): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new EmployeeResignationNotification(
                employeeName: $employeeName,
                resignDate: $this->resignDate,
                remark: $this->remark,
            ),
        );
    }
}
