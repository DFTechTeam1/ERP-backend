<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;

class ReviseTaskNotification extends Notification
{
    use Queueable;

    public $employee;

    public $task;

    public $revise;

    public $telegramChatIds;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $task, $revise)
    {
        $this->employee = $employee;

        $this->task = $task;

        $this->revise = $revise;

        $this->telegramChatIds = [$employee->telegram_chat_id];
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'database',
            TelegramChannel::class,
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
        return [
            'title' => __('global.reviseTask'),
            'message' => __('global.newReviseTask', ['taskName' => $this->task->name]),
            'link' => config('app.frontend_url') . '/admin/production/project/' . $this->task->project->uid,
        ];
    }

    public function toTelegram($notifiable): array
    {
        $images = json_decode($this->revise->file, true);

        if ($images) {
            $images = collect($images)->map(function ($item) {
                $path = asset('storage/projects/' . $this->revise->project_id . '/task/' . $this->revise->project_task_id . '/revise/' . $item);

                return [
                    'type' => 'photo',
                    'media' => $path
                ];
            })->toArray();
            // add caption on the first item
            $images[0]['caption'] = "Ini gambaran revisimu";

            if (env('APP_ENV') == 'local' && env('APP_URL') != 'https://backend.dfactory.pro') {
                $images = [
                    ['type' => 'photo', 'media' => env('STATIC_IMAGE'), 'caption' => 'Ini gambaran revisimu'],
                ];
            }
        }

        $messages = [
            'Halo ' . $this->employee->nickname . ' tugas ' . $this->task->name . ' di event ' . $this->task->project->name . ' harus di revisi nih.',
            'Revisinya karena ' . $this->revise->reason,
        ];

        if ($images) {
            $messages = collect($messages)->push([
                'type' => 'media_group',
                'text' => 'media_group',
                'photos' => $images
            ])->values()->toArray();
        }

        Log::debug('messages', $messages);

        return [
            'chatIds' => $this->telegramChatIds,
            'message' => $messages
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo ' . $this->employee->nickname . ' tugas ' . $this->task->name . ' di event ' . $this->task->project->name . ' harus di revisi nih.',
            ],
            [
                'type' => 'text',
                'text' => 'Revisinya karena ' . $this->revise->reason,
            ],
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];
    }
}
