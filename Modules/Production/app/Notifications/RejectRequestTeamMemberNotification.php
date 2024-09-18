<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RejectRequestTeamMemberNotification extends Notification
{
    use Queueable;

    private $lineIds;

    private $transfer;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $lineIds, \Modules\Production\Models\TransferTeamMember $transfer)
    {
        $this->lineIds = $lineIds;

        $this->transfer = $transfer;
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
        $message = 'Halo ' . $this->transfer->requestByPerson->nickname . '. Permintaan anda untuk meminjam ' . $this->transfer->employee->nickname . ' di tolak dengan alasan ' . $this->transfer->reject_reason;

        $messages = [
            [
                'type' => 'text',
                'text' => $message,
            ]
        ];

        if ($this->transfer->alternativeEmployee) {
            $messages[] = [
                'type' => 'text',
                'text' => $this->transfer->alternativeEmployee->nickname . ' sebagai penggantinya. Kamu sudah bisa mulai memberikan tugas.'
            ];   
        }

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
