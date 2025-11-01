<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class GenericNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $action;
    private string $message;
    private string $htmlMessage;
    private array $data;
    private array $options;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        string $action,
        string $message,
        string $htmlMessage,
        array $data = [],
        array $options = []
    ) {
        $this->action = $action;
        $this->message = $message;
        $this->htmlMessage = $htmlMessage;
        $this->data = $data;
        $this->options = $options;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->options['subject'] ?? $this->getSubject())
            ->greeting($this->options['greeting'] ?? 'Hello!')
            ->line($this->message);

        // Add action button if provided
        if (isset($this->options['action_url']) && isset($this->options['action_text'])) {
            $mail->action($this->options['action_text'], $this->options['action_url']);
        }

        // Add additional lines if provided
        if (isset($this->options['additional_lines']) && is_array($this->options['additional_lines'])) {
            foreach ($this->options['additional_lines'] as $line) {
                $mail->line($line);
            }
        }

        $mail->salutation($this->options['salutation'] ?? 'Regards, ' . config('app.name'));

        return $mail;
    }

    /**
     * Get default subject based on action
     */
    private function getSubject(): string
    {
        $subjects = [
            'user_has_been_assigned_to_task' => 'You have been assigned to a new task',
            'task_has_been_revise_by_pic' => 'Your task needs revision',
            'deadline_has_been_added' => 'New deadline added',
            'project_deal_has_been_approved' => 'Project deal approved',
            // Add more as needed
        ];

        return $subjects[$this->action] ?? 'New Notification';
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'action' => $this->action,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
