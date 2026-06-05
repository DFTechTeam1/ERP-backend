<?php

namespace Modules\Email\Services;

use App\Data\Whatsapp\CreateCommunityServerSchemaData;
use App\Data\Whatsapp\CreateGroupServerSchemaData;
use App\Data\Whatsapp\GenerateInviteLinkServerData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsappService
{
    private string $internalSecret;

    private string $whatsappUrl;

    public function __construct()
    {
        $this->internalSecret = config('app.internal_service_secret');
        $this->whatsappUrl = config('app.whatsapp_service');
    }

    private function buildHmacHeaders(string $method, string $path, array $body): array
    {
        $timestamp = (string) time();
        $rawBody = json_encode($body);
        $signingString = implode("\n", [strtoupper($method), $path, $rawBody, $timestamp]);
        $signature = hash_hmac('sha256', $signingString, $this->internalSecret);

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Service-Name' => 'laravel',
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ];
    }

    private function client(array $headers): PendingRequest
    {
        return Http::withHeaders($headers)->withoutVerifying();
    }

    public function sendWhatsappMessage(array $body): mixed
    {
        $headers = $this->buildHmacHeaders('POST', 'api/message/send', $body);
        $url = $this->whatsappUrl.'/message/send';
        $response = $this->client($headers)->post($url, $body);

        return $response->json();
    }

    public function addToWhatsappGroup(array $body): array
    {
        $headers = $this->buildHmacHeaders('POST', 'api/message/group/participant/add', $body);
        $url = $this->whatsappUrl.'/message/group/participant/add';
        $response = $this->client($headers)->post($url, $body);

        logging('RESULTS ADD TO WHATSAPP', [$response->json()]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Success add participant'];
        }

        return $response->json() ?? ['success' => false, 'message' => 'Failed to add participant'];
    }

    public function createGroup(CreateGroupServerSchemaData $body): array
    {
        $headers = $this->buildHmacHeaders('POST', 'api/message/community/group/create', $body->toArray());
        $url = $this->whatsappUrl.'/message/community/group/create';
        $response = $this->client($headers)->post($url, $body->toArray());

        logging('check data create group whatsapp', [$response->json()]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Success create group', 'data' => $response->json('data.id', null)];
        }

        return $response->json() ?? ['success' => false, 'message' => 'Failed to add participant'];
    }

    public function checkWhatsappNumber(string $phone): bool
    {
        $body = ['phone' => $phone];
        $headers = $this->buildHmacHeaders('POST', 'api/message/check-number', $body);
        $url = $this->whatsappUrl.'/message/check-number';
        $response = $this->client($headers)->post($url, $body);

        return $response->json('data.isAvailable', false);
    }

    public function createCommunity(CreateCommunityServerSchemaData $request): ?string
    {
        $body = $request->toArray();
        $headers = $this->buildHmacHeaders('POST', 'api/message/community/create', $body);
        $url = $this->whatsappUrl.'/message/community/create';
        $response = $this->client($headers)->post($url, $body);

        return $response->json('data.id', null);
    }

    public function checkNumberIsPresentOnGroup(string $phone, string $groupId): bool
    {
        $body = ['phone' => $phone, 'groupId' => $groupId];
        $headers = $this->buildHmacHeaders('POST', 'api/message/check-participant', $body);
        $url = $this->whatsappUrl.'/message/check-participant';
        $response = $this->client($headers)->post($url, $body);

        return $response->json('data.isPresent', false);
    }

    public function removeFromWhatsappGroup(array $body): array
    {
        $headers = $this->buildHmacHeaders('POST', 'api/message/group/participant/remove', $body);
        $url = $this->whatsappUrl.'/message/group/participant/remove';
        $response = $this->client($headers)->post($url, $body);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Success remove participant'];
        }

        return $response->json() ?? ['success' => false, 'message' => 'Failed to remove participant'];
    }

    public function getGroupInviteLink(GenerateInviteLinkServerData $payload): array
    {
        $body = ['groupId' => $payload->groupId];
        $headers = $this->buildHmacHeaders('POST', 'api/message/group/invite-link', $body);
        $url = $this->whatsappUrl.'/message/group/invite-link';
        $response = $this->client($headers)->post($url, $body);

        logging('invitation group response', [$response->json()]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Success get invite link', 'data' => $response->json('data')];
        }

        return $response->json() ?? ['success' => false, 'message' => 'Failed to get invite link'];
    }

    public function whatsappSync(string $groupId)
    {
        $body = ['groupId' => $groupId];
        $headers = $this->buildHmacHeaders('POST', 'api/message/group/sync', $body);
        $url = $this->whatsappUrl.'/message/group/sync';
        $response = $this->client($headers)->post($url, $body);

        logging('sync group response', [$response->json()]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Success sync group', 'data' => []];
        }

        return $response->json() ?? ['success' => false, 'message' => 'Failed to sync group'];
    }
}
