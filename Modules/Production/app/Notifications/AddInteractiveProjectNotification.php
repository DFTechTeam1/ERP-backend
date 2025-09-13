<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveRequest;

class AddInteractiveProjectNotification extends Notification
{
    use Queueable;

    private string $message;

    private Collection|Employee $employee;

    private Collection|InteractiveRequest $request;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $message, Collection|Employee $employee, Collection|InteractiveRequest $request)
    {
        $this->message = $message;
        $this->employee = $employee;
        $this->request = $request;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $output = [
            'mail',
        ];

        if ($this->employee->telegram_chat_id) {
            $output[] = TelegramChannel::class;
        }

        return $output;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Interactive Project Request')
            ->markdown('mail.deals.interactiveRequest', [
                'employee' => $this->employee,
                'request' => $this->request,
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }

    public function toTelegram($notifiable): array
    {
        return [
            'chatIds' => [
                $this->employee->telegram_chat_id,
            ],
            'message' => $this->message,
        ];
    }
}
