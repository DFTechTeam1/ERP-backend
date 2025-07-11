<?php

namespace App\Notifications;

use App\Exports\ProjectDealSummary;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;

class ProjectDealSummaryNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
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

        $filePath = "finance/reports/summary/Project Deal " . now()->format('Y-m-d') . ".xlsx";
        Excel::store(export: new ProjectDealSummary, filePath: $filePath, disk: "public");

        return (new MailMessage)
                    ->subject('Event Summary')
                    ->greeting("Dear Finance Team,")
                    ->line('Please find attached the daily report for Today '. Carbon::now()->format('d F Y'))
                    ->line('Thank you')
                    ->attach(storage_path("app/public/{$filePath}"));
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
