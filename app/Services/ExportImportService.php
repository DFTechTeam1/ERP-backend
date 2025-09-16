<?php

namespace App\Services;

use Modules\Company\Repository\ExportImportResultRepository;

class ExportImportService
{
    public function handleSuccessProcessing(array $payload, string $event = 'handle-export-import-notification')
    {
        $this->storeDatabase($payload);

        // send user notification via pusher
        (new PusherNotification)->send(
            channel: 'my-channel-'.$payload['user_id'],
            event: $event,
            payload: [
                'type' => 'exportImportSuccess',
                'message' => $payload['description'],
            ],
            compressedValue: true
        );
    }

    private function storeDatabase(array $payload)
    {
        (new ExportImportResultRepository)->store(data: $payload);
    }

    /**
     * Handle error processing when do export or import
     * send user notification
     *
     * @param  array  $payload  With these following structure
     *                          - string $description
     *                          - string $message
     *                          - string $area
     *                          - int $user_id
     * @return void
     */
    public function handleErrorProcessing(array $payload)
    {
        $this->storeDatabase($payload);

        // send user notification via pusher
        (new PusherNotification)->send(
            channel: 'my-channel-'.$payload['user_id'],
            event: 'handle-export-import-notification',
            payload: [
                'type' => 'exportImportFailed',
                'message' => $payload['description'],
            ],
            compressedValue: true
        );
    }
}
