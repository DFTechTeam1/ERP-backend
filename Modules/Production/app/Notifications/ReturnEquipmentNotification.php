<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturnEquipmentNotification extends Notification
{
    use Queueable;

    public $employee;

    public $telegramChatIds;

    public $project;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $project)
    {
        $this->employee = $employee;

        $this->project = $project;

        $this->telegramChatIds = [$employee->telegram_chat_id];
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'database',
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
        $link = config('frontend_url').'/admin/inventories/request-equipment/'.$this->project->uid;

        return [
            'title' => __('global.returnEquipment'),
            'message' => __('global.equipmentHasBeenReturned', ['event' => $this->project['name']]),
            'link' => $link,
        ];
    }

    public function toTelegram($notifiable): array
    {
        return [
            'chatIds' => $this->telegramChatIds,
            'message' => 'Halo '.$this->employee->name.'. Equipment untuk event '.$this->project->name.' sudah di kembalikan dan siap untuk di cek',
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo '.$this->employee->name.'. Equipment untuk event '.$this->project->name.' sudah di kembalikan dan siap untuk di cek',
            ],
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];
    }
}
