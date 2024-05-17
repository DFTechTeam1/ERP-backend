<?php

namespace Modules\Hrd\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class UserEmailActivation extends Notification
{
    use Queueable;

    public $password;

    public $user;

    public $encrypted;

    /**
     * Create a new notification instance.
     */
    public function __construct($user, $encryptedData)
    {
        $this->user = $user;

        $this->encrypted = $encryptedData;
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
        return (new MailMessage)
            ->subject('Email Activation')
            ->markdown('mail.user.EmailActivation', [
                'user' => $this->user,
                'urlActivate' => env('FRONTEND_URL') . '/activate/' . $this->encrypted
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
