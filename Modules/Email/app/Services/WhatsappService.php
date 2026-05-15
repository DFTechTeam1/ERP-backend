<?php

namespace Modules\Email\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class WhatsappService
{
    private string $internalSecret;
    private string $whatsappUrl;

    public function __construct()
    {
        $this->internalSecret = config('app.internal_service_secret');
        $this->whatsappUrl    = config('app.whatsapp_service');
    }

    private function buildHmacHeaders(string $method, string $path, array $body): array
    {
        $timestamp     = (string) time();
        $rawBody       = json_encode($body);
        $signingString = implode("\n", [strtoupper($method), $path, $rawBody, $timestamp]);
        $signature     = hash_hmac('sha256', $signingString, $this->internalSecret);

        return [
            'Accept'         => 'application/json',
            'Content-Type'   => 'application/json',
            'X-Service-Name' => 'laravel',
            'X-Timestamp'    => $timestamp,
            'X-Signature'    => $signature,
        ];
    }

    private function client(array $headers): PendingRequest
    {
        return Http::withHeaders($headers)->withoutVerifying();
    }

    public function sendWhatsappMessage(array $body): mixed
    {
        $headers  = $this->buildHmacHeaders('POST', 'api/message/send', $body);
        $url      = $this->whatsappUrl . '/message/send';
        $response = $this->client($headers)->post($url, $body);

        return $response->json();
    }

    public function addToWhatsappGroup(array $body): array
    {
        $headers  = $this->buildHmacHeaders('POST', 'api/message/group/participant/add', $body);
        $url      = $this->whatsappUrl . '/message/group/participant/add';
        $response = $this->client($headers)->post($url, $body);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Success add participant'];
        }

        return $response->json() ?? ['success' => false, 'message' => 'Failed to add participant'];
    }

    public function checkWhatsappNumber(string $phone): bool
    {
        $body     = ['phone' => $phone];
        $headers  = $this->buildHmacHeaders('POST', 'api/message/check-number', $body);
        $url      = $this->whatsappUrl . '/message/check-number';
        $response = $this->client($headers)->post($url, $body);

        return $response->json('data.isAvailable', false);
    }

    public function checkNumberIsPresentOnGroup(string $phone, string $groupId): bool
    {
        $body     = ['phone' => $phone, 'groupId' => $groupId];
        $headers  = $this->buildHmacHeaders('POST', 'api/message/check-participant', $body);
        $url      = $this->whatsappUrl . '/message/check-participant';
        $response = $this->client($headers)->post($url, $body);

        return $response->json('data.isPresent', false);
    }

    public function removeFromWhatsappGroup(array $body): array
    {
        $headers  = $this->buildHmacHeaders('POST', 'api/message/group/participant/remove', $body);
        $url      = $this->whatsappUrl . '/message/group/participant/remove';
        $response = $this->client($headers)->post($url, $body);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Success remove participant'];
        }

        return $response->json() ?? ['success' => false, 'message' => 'Failed to remove participant'];
    }
}
