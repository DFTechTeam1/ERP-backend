<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CancelRequestTeamMemberNotification extends Notification
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
        return [
            TelegramChannel::class,
        ];
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
            'message' => 'Halo '.$this->transfer->requestToPerson->name.', '.$this->transfer->requestByPerson->name.' telah membatalkan permintaan request untuk '.$this->transfer->employee->name.'.',
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo '.$this->transfer->requestToPerson->name.', '.$this->transfer->requestByPerson->name.' telah membatalkan permintaan request untuk '.$this->transfer->employee->name.'.',
            ],
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];
    }
}
