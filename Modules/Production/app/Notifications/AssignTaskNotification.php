<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AssignTaskNotification extends Notification
{
    use Queueable;

    public $lineIds;

    public $task;

    /**
     * Create a new notification instance.
     */
    public function __construct($lineIds, $task)
    {
        $this->lineIds = $lineIds;

        $this->task = $task;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [\App\Notifications\LineChannel::class];
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

    public function toLine($notifiable)
    {
        $postbackApprove = 'approveTask&taskUid=' . $this->task->uid . '&projectUid=' . $this->task->project->uid;

        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo, kamu mendapat tugas baru nih di event ' . $this->task->project->name . ' - ' . $this->task->name . "\nSilahkan login untuk melihat detailnya.",
            ],
            [
                'type' => 'template',
                'altText' => 'Tugas Baru',
                'template' => [
                    'type' => 'buttons',
                    'text' => 'Apakah kamu ingin menerima tugas ini?',
                    'actions' => [
                        [
                            'type' => 'postback',
                            'label' => __('global.approve'),
                            'data' => $postbackApprove,
                        ],
                        [
                            'type' => 'postback',
                            'label' => __('global.reject'),
                            'data' => 'action=reject',
                        ],
                    ]
                ]
            ],
        ];

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
