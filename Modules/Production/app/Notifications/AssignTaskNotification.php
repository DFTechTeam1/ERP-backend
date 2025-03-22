<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Vinkla\Hashids\Facades\Hashids;

class AssignTaskNotification extends Notification
{
    use Queueable;

    public $chatIds;

    public $task;

    public $employeeId;

    public $userData;

    /**
     * Create a new notification instance.
     */
    public function __construct($chatIds, $task, $employeeId, $userData)
    {
        $this->task = $task;

        $this->employeeId = $employeeId;

        $this->chatIds = $chatIds;

        $this->userData = $userData;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [TelegramChannel::class];
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
        $message = [
            'Halo, kamu mendapat tugas baru nih di event ' . $this->task->project->name . ' - ' . $this->task->name . "\nSilahkan login untuk melihat detailnya.",
            [
                'text' => 'Setujui tugas ini?',
                'type' => 'inline_keyboard',
                'keyboard' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Terima pekerjaan', 'callback_data' => 'idt=ptappv&eid=' . $this->employeeId . '&tid=' . $this->task->id . '&pid=' . $this->task->project->id],
                        ]
                    ]
                ]
            ]
        ];

        // message for lead modeller
        if ($this->userData->hasPermissionTo('assign_modeller')) {
            $message = [
                "Halo, tugas {$this->task->name} di event {$this->task->project->name} siap untuk kamu distribusikan ke tim kamu"
            ];
        }

        return [
            'chatIds' => $this->chatIds,
            'message' => $message
        ];
    }

    public function toLine($notifiable)
    {
        $divider = \App\Enums\CodeDivider::assignTaskJobDivider->value;

        $payloadEncrypted = $this->task->id . $divider . $this->employeeId . $divider . $this->task->project->id;

        $postbackApprove = 'approveTask=' . Hashids::encode($payloadEncrypted);

        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo, kamu mendapat tugas baru nih di event ' . $this->task->project->name . ' - ' . $this->task->name . "\nSilahkan login untuk melihat detailnya.",
            ],
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];

    }
}
