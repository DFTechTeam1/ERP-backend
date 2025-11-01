<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Unified Notification Service
 * 
 * This service handles all notification delivery across multiple channels:
 * - Email
 * - Slack
 * - Telegram (via microservice)
 * - Database
 * 
 * Usage:
 * NotificationService::send($user, 'user_assigned_to_task', $data, ['email', 'database']);
 */
class NotificationService
{
    /**
     * Available notification channels
     */
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SLACK = 'slack';
    const CHANNEL_TELEGRAM = 'telegram';
    const CHANNEL_DATABASE = 'database';

    /**
     * All available channels
     */
    const ALL_CHANNELS = [
        self::CHANNEL_EMAIL,
        self::CHANNEL_SLACK,
        self::CHANNEL_TELEGRAM,
        self::CHANNEL_DATABASE,
    ];

    /**
     * Send notification to specified channels
     * 
     * @param User|array $recipients - Single user or array of users
     * @param string $action - Notification action/type from notification_settings
     * @param array $data - Data for template parameters
     * @param array $channels - Channels to send notification (email, slack, telegram, database)
     * @param array $options - Additional options (attachments, images, etc)
     * @return array - Result of each channel delivery
     */
    public static function send(
        $recipients,
        string $action,
        array $data = [],
        array $channels = [self::CHANNEL_DATABASE],
        array $options = []
    ): array {
        // Normalize recipients to array
        $recipients = is_array($recipients) ? $recipients : [$recipients];
        
        $results = [];
        
        foreach ($recipients as $recipient) {
            $recipientResults = self::sendToRecipient($recipient, $action, $data, $channels, $options);
            $results[] = $recipientResults;
        }
        
        return $results;
    }

    /**
     * Send notification to a single recipient
     * 
     * @param User $recipient
     * @param string $action
     * @param array $data
     * @param array $channels
     * @param array $options
     * @return array
     */
    private static function sendToRecipient(
        User $recipient,
        string $action,
        array $data,
        array $channels,
        array $options
    ): array {
        $results = [
            'recipient' => $recipient->email ?? $recipient->id,
            'action' => $action,
            'channels' => [],
        ];

        // Get notification template
        $template = self::getTemplate($action);
        
        if (!$template) {
            Log::warning('Notification template not found', [
                'action' => $action,
                'recipient' => $recipient->id,
            ]);
            
            $results['error'] = 'Template not found';
            return $results;
        }

        // Process template with data
        $message = self::processTemplate($template->template, $data);
        $htmlMessage = self::processTemplate($template->template_html, $data, true);

        // Send to each channel
        foreach ($channels as $channel) {
            try {
                $channelResult = self::sendToChannel(
                    $channel,
                    $recipient,
                    $action,
                    $message,
                    $htmlMessage,
                    $data,
                    $options
                );
                
                $results['channels'][$channel] = $channelResult;
            } catch (Exception $e) {
                Log::error("Failed to send notification via {$channel}", [
                    'recipient' => $recipient->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $results['channels'][$channel] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Send notification to specific channel
     * 
     * @param string $channel
     * @param User $recipient
     * @param string $action
     * @param string $message
     * @param string $htmlMessage
     * @param array $data
     * @param array $options
     * @return array
     */
    private static function sendToChannel(
        string $channel,
        User $recipient,
        string $action,
        string $message,
        string $htmlMessage,
        array $data,
        array $options
    ): array {
        switch ($channel) {
            case self::CHANNEL_EMAIL:
                return self::sendEmail($recipient, $action, $message, $htmlMessage, $data, $options);
                
            case self::CHANNEL_SLACK:
                return self::sendSlack($recipient, $action, $message, $data, $options);
                
            case self::CHANNEL_TELEGRAM:
                return self::sendTelegram($recipient, $action, $message, $data, $options);
                
            case self::CHANNEL_DATABASE:
                return self::sendDatabase($recipient, $action, $message, $data, $options);
                
            default:
                throw new Exception("Unknown notification channel: {$channel}");
        }
    }

    /**
     * Send email notification using Laravel Notification
     * 
     * @param User $recipient
     * @param string $action
     * @param string $message
     * @param string $htmlMessage
     * @param array $data
     * @param array $options
     * @return array
     */
    private static function sendEmail(
        User $recipient,
        string $action,
        string $message,
        string $htmlMessage,
        array $data,
        array $options
    ): array {
        try {
            // Create notification class dynamically or use a generic one
            $notification = new \App\Notifications\GenericNotification(
                $action,
                $message,
                $htmlMessage,
                $data,
                $options
            );
            
            $recipient->notify($notification);
            
            Log::info('Email notification sent', [
                'recipient' => $recipient->email,
                'action' => $action,
            ]);
            
            return [
                'success' => true,
                'channel' => 'email',
                'sent_at' => now()->toDateTimeString(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to send email notification', [
                'recipient' => $recipient->email,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'channel' => 'email',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send Slack notification using Laravel Notification
     * 
     * @param User $recipient
     * @param string $action
     * @param string $message
     * @param array $data
     * @param array $options
     * @return array
     */
    private static function sendSlack(
        User $recipient,
        string $action,
        string $message,
        array $data,
        array $options
    ): array {
        try {
            // Create Slack notification
            $notification = new \App\Notifications\SlackNotification(
                $action,
                $message,
                $data,
                $options
            );
            
            $recipient->notify($notification);
            
            Log::info('Slack notification sent', [
                'recipient' => $recipient->id,
                'action' => $action,
            ]);
            
            return [
                'success' => true,
                'channel' => 'slack',
                'sent_at' => now()->toDateTimeString(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to send Slack notification', [
                'recipient' => $recipient->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'channel' => 'slack',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build query parameters for Telegram microservice
     * 
     * @param array $payload
     * @return string
     */
    protected function buildTelegramParams(array $payload): string
    {
        $url = config('app.python_endpoint') . '/notification/send-telegram';

        $params = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $params[] = $key . '[]=' . urlencode($item);
                }
            } else {
                $params[] = $key . '=' . urlencode($value);
            }
        }

        $url .= '?' . implode('&', $params);

        return $url;
    }

    /**
     * Send Telegram notification via microservice
     * 
     * @param User $recipient
     * @param string $action
     * @param string $message
     * @param array $data
     * @param array $options
     * @return array
     */
    private static function sendTelegram(
        User $recipient,
        string $action,
        string $message,
        array $data,
        array $options
    ): array {
        try {
            // Get Telegram chat ID from user profile or settings
            $telegramChatId = $recipient->telegram_chat_id ?? null;
            
            if (!$telegramChatId) {
                Log::warning('Telegram chat ID not found for user', [
                    'user_id' => $recipient->id,
                ]);
                
                return [
                    'success' => false,
                    'channel' => 'telegram',
                    'error' => 'Telegram chat ID not found',
                ];
            }

            // Get Telegram microservice endpoint
            $telegramEndpoint = config('app.telegram_endpoint') ?? config('services.telegram.endpoint');
            
            if (!$telegramEndpoint) {
                throw new Exception('Telegram endpoint not configured');
            }

            // Prepare payload for microservice
            $payload = [
                'chat_id' => $telegramChatId,
                'message' => $message,
                'action' => $action,
                'data' => $data,
            ];

            // Add optional parameters
            if (isset($options['parse_mode'])) {
                $payload['parse_mode'] = $options['parse_mode'];
            }

            if (isset($options['reply_markup'])) {
                $payload['reply_markup'] = $options['reply_markup'];
            }

            // Send request to microservice
            $response = Http::timeout(30)
                ->post($telegramEndpoint . '/send-notification', $payload);

            if ($response->successful()) {
                Log::info('Telegram notification sent', [
                    'recipient' => $recipient->id,
                    'chat_id' => $telegramChatId,
                    'action' => $action,
                ]);
                
                return [
                    'success' => true,
                    'channel' => 'telegram',
                    'sent_at' => now()->toDateTimeString(),
                    'response' => $response->json(),
                ];
            } else {
                Log::error('Telegram microservice returned error', [
                    'recipient' => $recipient->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return [
                    'success' => false,
                    'channel' => 'telegram',
                    'error' => 'Microservice error: ' . $response->body(),
                    'status_code' => $response->status(),
                ];
            }
        } catch (Exception $e) {
            Log::error('Failed to send Telegram notification', [
                'recipient' => $recipient->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'channel' => 'telegram',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send database notification using Laravel Notification
     * 
     * @param User $recipient
     * @param string $action
     * @param string $message
     * @param array $data
     * @param array $options
     * @return array
     */
    private static function sendDatabase(
        User $recipient,
        string $action,
        string $message,
        array $data,
        array $options
    ): array {
        try {
            // Create database notification
            $notification = new \App\Notifications\DatabaseNotification(
                $action,
                $message,
                $data,
                $options
            );
            
            $recipient->notify($notification);
            
            Log::info('Database notification saved', [
                'recipient' => $recipient->id,
                'action' => $action,
            ]);
            
            return [
                'success' => true,
                'channel' => 'database',
                'saved_at' => now()->toDateTimeString(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to save database notification', [
                'recipient' => $recipient->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'channel' => 'database',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get notification template from database
     * 
     * @param string $action
     * @return object|null
     */
    private static function getTemplate(string $action): ?object
    {
        return DB::table('notification_settings')
            ->where('action', $action)
            ->first();
    }

    /**
     * Process template with data parameters
     * 
     * Template placeholders:
     * - <parameter1>, <parameter2>, ..., <parameterN> for text values
     * - <image1>, <image2>, ..., <imageN> for image URLs
     * - <audio1>, <audio2>, ..., <audioN> for audio URLs
     * - <document1>, <document2>, ..., <documentN> for document URLs
     * - <bubble> for new line
     * 
     * @param string $template
     * @param array $data
     * @param bool $isHtml
     * @return string
     */
    private static function processTemplate(string $template, array $data, bool $isHtml = false): string
    {
        $message = $template;
        
        // Replace parameter placeholders (parameter1, parameter2, ..., parameterN)
        foreach ($data as $key => $value) {
            // Skip special arrays (images, audios, documents)
            if (in_array($key, ['images', 'audios', 'documents'])) {
                continue;
            }
            
            if ($isHtml) {
                $message = str_replace("&lt;{$key}&gt;", htmlspecialchars($value), $message);
            } else {
                $message = str_replace("<{$key}>", $value, $message);
            }
        }
        
        // Handle images (image1, image2, ..., imageN)
        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $key => $url) {
                if ($isHtml) {
                    $imageTag = "<img src=\"{$url}\" alt=\"{$key}\" style=\"max-width: 100%; height: auto;\">";
                    $message = str_replace("&lt;{$key}&gt;", $imageTag, $message);
                } else {
                    $message = str_replace("<{$key}>", $url, $message);
                }
            }
        }
        
        // Handle audios (audio1, audio2, ..., audioN)
        if (isset($data['audios']) && is_array($data['audios'])) {
            foreach ($data['audios'] as $key => $url) {
                if ($isHtml) {
                    $audioTag = "<audio controls><source src=\"{$url}\" type=\"audio/mpeg\">Your browser does not support the audio element.</audio>";
                    $message = str_replace("&lt;{$key}&gt;", $audioTag, $message);
                } else {
                    $message = str_replace("<{$key}>", $url, $message);
                }
            }
        }
        
        // Handle documents (document1, document2, ..., documentN)
        if (isset($data['documents']) && is_array($data['documents'])) {
            foreach ($data['documents'] as $key => $url) {
                if ($isHtml) {
                    $filename = basename($url);
                    $documentTag = "<a href=\"{$url}\" target=\"_blank\" download>📄 {$filename}</a>";
                    $message = str_replace("&lt;{$key}&gt;", $documentTag, $message);
                } else {
                    $message = str_replace("<{$key}>", $url, $message);
                }
            }
        }
        
        // Replace <bubble> with new line
        if ($isHtml) {
            $message = str_replace('&lt;bubble&gt;', '<br>', $message);
        } else {
            $message = str_replace('<bubble>', "\n", $message);
        }
        
        // Clean up if not HTML
        if (!$isHtml) {
            $message = strip_tags($message);
        }
        
        return $message;
    }

    /**
     * Send notification asynchronously using Job
     * 
     * @param User|array $recipients
     * @param string $action
     * @param array $data
     * @param array $channels
     * @param array $options
     * @return void
     */
    public static function sendAsync(
        $recipients,
        string $action,
        array $data = [],
        array $channels = [self::CHANNEL_DATABASE],
        array $options = []
    ): void {
        \App\Jobs\SendNotificationJob::dispatch($recipients, $action, $data, $channels, $options);
    }

    /**
     * Get user's notification preferences
     * 
     * @param User $user
     * @param string $action
     * @return array
     */
    public static function getUserChannels(User $user, string $action): array
    {
        // Get user preferences from database or default settings
        $preferences = $user->notification_preferences ?? [];
        
        if (isset($preferences[$action])) {
            return $preferences[$action];
        }
        
        // Default channels if no preference set
        return [self::CHANNEL_DATABASE];
    }

    /**
     * Check if channel is available for user
     * 
     * @param User $user
     * @param string $channel
     * @return bool
     */
    public static function isChannelAvailable(User $user, string $channel): bool
    {
        switch ($channel) {
            case self::CHANNEL_EMAIL:
                return !empty($user->email);
                
            case self::CHANNEL_SLACK:
                return !empty($user->slack_webhook_url) || !empty($user->slack_channel);
                
            case self::CHANNEL_TELEGRAM:
                return !empty($user->telegram_chat_id);
                
            case self::CHANNEL_DATABASE:
                return true; // Always available
                
            default:
                return false;
        }
    }

    /**
     * Validate channels and filter unavailable ones
     * 
     * @param User $user
     * @param array $channels
     * @return array
     */
    public static function validateChannels(User $user, array $channels): array
    {
        return array_filter($channels, function ($channel) use ($user) {
            return self::isChannelAvailable($user, $channel);
        });
    }
}
