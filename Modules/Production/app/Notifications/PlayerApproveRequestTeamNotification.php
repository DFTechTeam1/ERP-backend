<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PlayerApproveRequestTeamNotification extends Notification
{
    use Queueable;

    private $transfer;

    private $lineIds;

    /**
     * Create a new notification instance.
     */
    public function __construct(\Modules\Production\Models\TransferTeamMember $transfer, array $lineIds)
    {
        $this->transfer = $transfer;

        $this->lineIds = $lineIds;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [\App\Notifications\LineChannel::class];
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
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
