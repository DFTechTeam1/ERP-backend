<?php

namespace Modules\Hrd\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Informational email sent to the resigning employee, their boss and the HR team
 * when a resignation is processed in the ERP.
 */
class EmployeeResignationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public string $resignDate,
        public ?string $remark = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('notification.resignationEmailSubject', ['name' => $this->employeeName]))
            ->greeting(__('notification.resignationEmailGreeting'))
            ->line(__('notification.resignationEmailBody', [
                'name' => $this->employeeName,
                'date' => date('d F Y', strtotime($this->resignDate)),
            ]));

        if (! empty($this->remark)) {
            $mail->line(__('notification.resignationEmailRemark', ['remark' => $this->remark]));
        }

        return $mail->line(__('notification.resignationEmailClosing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [];
    }
}
