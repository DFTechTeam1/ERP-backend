<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AssignCheckByPMNotification extends Notification
{
    use Queueable;

    public $chatIds;

    public $task;

    public $employeeId;

    /**
     * Create a new notification instance.
     */
    public function __construct($chatIds, $task, $employeeId)
    {
        $this->task = $task;

        $this->employeeId = $employeeId;

        $this->chatIds = $chatIds;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [TelegramChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', 'https://laravel.com')
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }

    public function toTelegram($notifiable)
    {
        $message = "Tugas *{$this->task->name}* di event *{$this->task->project->name}* sudah selesai.";

        return [
            'chatIds' => $this->chatIds,
            'message' => [
                $message,
                [
                    'text' => 'Klik tombol dibawah kl kamu ingin melihat hasil pekerjaannya',
                    'type' => 'inline_keyboard',
                    'keyboard' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Cek Hasil Pekerjaan', 'callback_data' => 'idt=' . \Modules\Telegram\Enums\CallbackIdentity::CheckProofOfWork->value . '&tid=' . $this->task->id],
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
