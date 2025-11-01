<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 120;

    /**
     * Notification data
     */
    private $recipients;
    private string $action;
    private array $data;
    private array $channels;
    private array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(
        $recipients,
        string $action,
        array $data = [],
        array $channels = [NotificationService::CHANNEL_DATABASE],
        array $options = []
    ) {
        $this->recipients = $recipients;
        $this->action = $action;
        $this->data = $data;
        $this->channels = $channels;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Sending notification via job', [
                'action' => $this->action,
                'channels' => $this->channels,
            ]);

            $results = NotificationService::send(
                $this->recipients,
                $this->action,
                $this->data,
                $this->channels,
                $this->options
            );

            Log::info('Notification sent successfully via job', [
                'action' => $this->action,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification via job', [
                'action' => $this->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rethrow to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job failed permanently', [
            'action' => $this->action,
            'channels' => $this->channels,
            'error' => $exception->getMessage(),
        ]);
    }
}
