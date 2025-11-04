<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class DatabaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $action;
    private string $message;
    private array $data;
    private array $options;
    private string $databaseType;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        string $action,
        string $message,
        array $data = [],
        array $options = [],
        string $databaseType = ''
    ) {
        $this->action = $action;
        $this->message = $message;
        $this->data = $data;
        $this->options = $options;
        $this->databaseType = $databaseType;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    public function databaseType(): string
    {
        return $this->databaseType == '' ? get_class($this) : $this->databaseType;
    }

    /**
     * Get the array representation of the notification for database.
     */
    public function toArray($notifiable): array
    {
        return [
            'action' => $this->action,
            'message' => $this->message,
            'title' => $this->options['title'] ?? $this->getTitle(),
            'icon' => $this->options['icon'] ?? $this->getIcon(),
            'url' => $this->options['url'] ?? null,
            'data' => $this->data,
            'read' => false,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get default title based on action
     */
    private function getTitle(): string
    {
        $titles = [
            'user_has_been_assigned_to_task' => 'New Task Assignment',
            'task_has_been_revise_by_pic' => 'Task Revision Required',
            'deadline_has_been_added' => 'Deadline Added',
            'project_deal_has_been_approved' => 'Project Approved',
            'user_has_been_removed_from_task' => 'Task Removal',
            'user_submit_their_task_with_image' => 'Task Completed',
            'pic_has_been_assigned_to_event' => 'PIC Assignment',
            'task_has_been_hold_by_user' => 'Task On Hold',
            'interactive_event_has_been_approved' => 'Event Approved',
        ];

        return $titles[$this->action] ?? 'Notification';
    }

    /**
     * Get default icon based on action
     */
    private function getIcon(): string
    {
        $icons = [
            'user_has_been_assigned_to_task' => '📋',
            'task_has_been_revise_by_pic' => '🔄',
            'deadline_has_been_added' => '⏰',
            'project_deal_has_been_approved' => '🎉',
            'user_has_been_removed_from_task' => '❌',
            'user_submit_their_task_with_image' => '✅',
            'pic_has_been_assigned_to_event' => '🎯',
            'task_has_been_hold_by_user' => '⏸️',
            'interactive_event_has_been_approved' => '🎊',
        ];

        return $icons[$this->action] ?? '🔔';
    }
}
