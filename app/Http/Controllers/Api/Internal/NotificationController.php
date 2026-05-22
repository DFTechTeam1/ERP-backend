<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\SendNotificationRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Send a notification from an internal service (Express, Python, etc.).
     *
     * The recipient is resolved by email. The action must match a row in
     * the notification_settings table. Channels default to ['email'] when
     * the request omits them.
     */
    public function send(SendNotificationRequest $request): \Illuminate\Http\JsonResponse
    {
        $recipient = User::where('email', $request->recipient_email)->first();

        if (! $recipient) {
            return apiResponse(
                generalResponse(
                    message: 'Recipient not found.',
                    error: true,
                    code: 404,
                ),
            );
        }

        $channels = $request->channels;
        $data = $request->data ?? [];
        $options = $request->options ?? [];
        $serviceName = $request->header('X-Service-Name');

        Log::info('Internal notification request received', [
            'service'   => $serviceName,
            'recipient' => $request->recipient_email,
            'action'    => $request->action,
            'channels'  => $channels,
        ]);

        NotificationService::sendAsync(
            recipients: $recipient,
            action: $request->action,
            data: $data,
            channels: $channels,
            options: $options,
        );

        return apiResponse(
            generalResponse(
                message: 'Notification queued successfully.',
                data: [
                    'recipient' => $recipient->email,
                    'action'    => $request->action,
                    'channels'  => $channels,
                ],
            ),
        );
    }
}
