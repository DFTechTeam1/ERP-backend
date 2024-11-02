<?php

namespace Modules\Inventory\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;

class NewRequestInventoryNotification extends Notification
{
    use Queueable;

    public $data;

    public $employee;

    public $requester;

    /**
     * Create a new notification instance.
     */
    public function __construct(object $data, object $employee, object $requester)
    {
        $this->data = $data;
        $this->employee = $employee;
        $this->requester = $requester;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            TelegramChannel::class
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
        $messages = [];
        $messages[] = [
            "Halo {$this->employee->nickname}, ada permintaan inventori baru dari " . $this->requester->nickname . "\n\nPerkiraan harganya adalah " . $this->data->price . "\nJumlah yang di minta sebanyak " . $this->data->quantity . " pcs",
            "Login untuk menyetujui permintaan ini 🙂"
        ];
        if ($this->data->purchase_source == 'online') {
            // show preview link on the telegram
            foreach ($this->data->purchase_link as $link) {
                $messages[] = [
                    'text' => $this->data->name,
                    'link_previews' => [
                        'url' => $link,
                        'show_above_text' => true,
                        'prefer_large_media' => true,
                    ]
                ];
            }
        } else if ($this->data->purchase_source == 'instore') {
            // only show the store name
            $messages[0][0] .= "\n\nBarang nya adalah\n" . $this->data->name . " ({$this->data->quantity} pcs) rencana dibeli dari {$this->data->store_name}";
        }

        return [
            'chatIds' => [$this->employee->telegram_chat_id],
            'message' => $messages
        ];
    }
}
