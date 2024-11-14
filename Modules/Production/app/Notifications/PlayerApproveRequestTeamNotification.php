<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PlayerApproveRequestTeamNotification extends Notification
{
    use Queueable;

    private $transfer;

    private $telegramChatIds;

    /**
     * Create a new notification instance.
     */
    public function __construct(\Modules\Production\Models\TransferTeamMember $transfer, array $telegramChatIds)
    {
        $this->transfer = $transfer;

        $this->telegramChatIds = $telegramChatIds;
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

    public function toTelegram($notifiable): array
    {
        return [
            'chatIds' => $this->telegramChatIds,
            'message' => [
                'Halo ' . $this->transfer->employee->nickname . ', ' . $this->transfer->requestByPerson->nickname . ' membutuhkan bantuan untuk mengerjakan tugas di event ' . $this->transfer->project->name,
                $this->transfer->requestToPerson->nickname . ' sudah setuju. Kamu akan segera menerima notifikasi tugas yang akan dikerjakan.',
            ]
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo ' . $this->transfer->employee->nickname . ', ' . $this->transfer->requestByPerson->nickname . ' membutuhkan bantuan untuk mengerjakan tugas di event ' . $this->transfer->project->name,
            ],
            [
                'type' => 'text',
                'text' => $this->transfer->requestToPerson->nickname . ' sudah setuju. Kamu akan segera menerima notifikasi tugas yang akan dikerjakan.',
            ],
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];
    }
}
