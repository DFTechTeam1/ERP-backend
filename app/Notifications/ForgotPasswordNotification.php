<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForgotPasswordNotification extends Notification
{
    use Queueable;

    private $user;

    public $url;

    /**
     * Create a new notification instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
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

        $service = new \App\Services\EncryptionService();

        $exp = date('Y-m-d H:i:s', strtotime('+1 Hour'));

        $encryptedPayload = $service->encrypt(json_encode([
            'email' => $this->user->email,
            'id' => $this->user->id,
            'exp' => $exp,
        ]), config('app.saltKey'));

        \App\Models\User::where("email", $this->user->email)
            ->update([
                'reset_password_token_claim' => false,
                'reset_password_token_exp' => $exp,
            ]);

        $this->url = config('app.frontend_url') . '/auth/a/reset-password?d=' . $encryptedPayload;

        logging('mail', [config('mail.mailers.smtp')]);

        return (new MailMessage)
                    ->subject('Ubah Password')
                    ->markdown('mail.user.forgotPassword', ['url' => $this->url]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
