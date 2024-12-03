<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewPicNotification extends Notification
{
    use Queueable;

    public $employee;

    public $chatIds;

    public $project;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $project)
    {
        $this->employee = $employee;

        $this->project = $project;

        $this->chatIds = [$employee->telegram_chat_id];
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            \App\Notifications\TelegramChannel::class,
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

    public function toTelegram($notifiable)
    {
        return [
            'chatIds' => $this->chatIds,
            'message' => 'Halo ' . $this->employee->nickname . ". Kamu ditugaskan untuk handle Event " . $this->project->name . " di tanggal " . date('d F Y', strtotime($this->project->project_date)) . "\nLogin untuk melihat detail event."
        ];
    }
}
