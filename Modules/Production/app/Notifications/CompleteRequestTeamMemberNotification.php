<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class CompleteRequestTeamMemberNotification extends Notification
{
    use Queueable;

    private $telegramChatIds;

    private $transfer;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $telegramChatIds, \Modules\Production\Models\TransferTeamMember $transfer)
    {
        $this->telegramChatIds = $telegramChatIds;

        $this->transfer = $transfer;
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
            'message' =>'Halo ' . $this->transfer->requestToPerson->nickname . '. ' . $this->transfer->employee->nickname . ' telah menyelesaikan semua tugas pada event ' . $this->transfer->project->name . '. Transfer team member sekarang sudah selesai dan ' . $this->transfer->employee->nickname . ' telah bisa mengerjakan tugas2 yang anda berikan secara utuh',
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo ' . $this->transfer->requestToPerson->nickname . '. ' . $this->transfer->employee->nickname . ' telah menyelesaikan semua tugas pada event ' . $this->transfer->project->name . '. Transfer team member sekarang sudah selesai dan ' . $this->transfer->employee->nickname . ' telah bisa mengerjakan tugas2 yang anda berikan secara utuh',
            ]
        ];

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
