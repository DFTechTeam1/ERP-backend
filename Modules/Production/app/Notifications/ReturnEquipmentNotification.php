<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ReturnEquipmentNotification extends Notification
{
    use Queueable;

    public $employee;

    public $lineIds;

    public $project;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $project)
    {
        $this->employee = $employee;

        $this->project = $project;

        $this->lineIds = [$employee->line_id];
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'database',
            \App\Notifications\LineChannel::class
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
        $link = config('frontend_url') . '/admin/inventories/request-equipment/' . $this->project->uid;
        
        return [
            'title' => __('global.returnEquipment'),
            'message' => __('global.equipmentHasBeenReturned', ['event' => $this->project['name']]),
            'link' => $link,
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => "Halo " . $this->employee->name . ". Equipment untuk event " . $this->project->name . " sudah di kembalikan dan siap untuk di cek",
            ],
        ];

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
