<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveRequest;

class AddInteractiveProjectNotification extends Notification
{
    use Queueable;

    private string $message;

    private Collection|Employee $employee;

    private Collection|InteractiveRequest $request;

    private int $userId;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $message, Collection|Employee $employee, Collection|InteractiveRequest $request, int $userId)
    {
        $this->message = $message;
        $this->employee = $employee;
        $this->request = $request;
        $this->userId = $userId;
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
        setEmailConfiguration();
        $requestId = Crypt::encryptString($this->request->id);

        return (new MailMessage)
            ->subject('New Interactive Project Request')
            ->markdown('mail.deals.interactiveRequest', [
                'employee' => $this->employee,
                'request' => $this->request,
                'requestId' => $requestId,
                'userId' => $this->userId,
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
