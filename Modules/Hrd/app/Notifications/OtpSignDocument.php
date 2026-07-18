<?php

namespace Modules\Hrd\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpSignDocument extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly string $otp,
        private readonly int $expiresInMinutes = 5,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        setEmailConfiguration();

        return (new MailMessage)
            ->subject(__('global.otpSignDocumentSubject'))
            ->markdown('mail.hrd.signature.otpSignDocument', [
                'otp' => $this->otp,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'expires_in_minutes' => $this->expiresInMinutes,
        ];
    }
}
