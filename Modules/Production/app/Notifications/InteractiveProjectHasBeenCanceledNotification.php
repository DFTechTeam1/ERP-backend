<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Hrd\Models\Employee;

class InteractiveProjectHasBeenCanceledNotification extends Notification
{
    use Queueable;

    protected Employee $employee;

    protected string $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(Employee $employee, string $message)
    {
        $this->employee = $employee;
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $output = [
            'mail',
            'database',
        ];

        if ($this->employee->telegram_chat_id) {
            $output[] = TelegramChannel::class;
        }

        return $output;
    }

    /**
     * Get the database connection to be used by the notification.
     */
    public function databaseType(object $notifiable): string
    {
        return 'production';
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
    public function toDatabase($notifiable): array
    {
        return [
            'message' => $this->message,
        ];
    }

    public function toTelegram($notifiable): array
    {
        return [
            'chatIds' => [$this->employee->telegram_chat_id],
            'message' => $this->message,
        ];
    }
}
