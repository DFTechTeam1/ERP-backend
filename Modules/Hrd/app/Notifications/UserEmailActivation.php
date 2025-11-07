<?php

namespace Modules\Hrd\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserEmailActivation extends Notification
{
    use Queueable;

    public $password;

    public $user;

    public $encrypted;

    /**
     * Create a new notification instance.
     */
    public function __construct($user, $encryptedData, $password)
    {
        $this->user = $user;

        $this->encrypted = $encryptedData;

        $this->password = $password;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'mail',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        setEmailConfiguration();

        return (new MailMessage)
            ->subject('Email Activation')
            ->markdown('mail.user.EmailActivation', [
                'user' => $this->user,
                'password' => $this->password,
                'urlActivate' => config('app.frontend_url').'/activate/'.$this->encrypted,
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }

    public function toLine(object $notifiable)
    {
        return [
            'message' => 'Message LINE',
        ];
    }
}
