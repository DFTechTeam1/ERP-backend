<?php

namespace Modules\Hrd\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TalentaService {
    private $url;

    private $endpoint;

    private $token;

    private $dateRequest;

    private $requestMethod;

    private $urlParam = '';

    private $payload = [];

    /**
     * Set endpoint and main url
     *
     * @param string $type
     * @return void
     */
    public function setUrl(string $type)
    {
        $this->endpoint = config("talenta.endpoint_list.{$type}");
        $this->url = config('talenta.base_uri') . $this->endpoint;
        $this->requestMethod = config("talenta.endpoint_method.{$type}");
    }

    /**
     * Set url query parameters
     *
     * @param array $params
     * @return void
     */
    public function setUrlParams(array $params)
    {
        $this->payload = $params;
    }

    /**
     * Generate authorization token
     *
     * @return void
     */
    protected function generateHmac(): void
    {
        $datetime       = Carbon::now()->toRfc7231String();
        $request_line   = "GET {$this->endpoint} HTTP/1.1";
        $payload        = implode("\n", ["date: {$datetime}", $request_line]);
        $digest         = hash_hmac('sha256', $payload, config('talenta.client_secret'), true);
        $signature      = base64_encode($digest);

        $clientId       = config('talenta.client_id');
        $completeSecret = "hmac username=\"{$clientId}\", algorithm=\"hmac-sha256\", headers=\"date request-line\", signature=\"{$signature}\"";

        $this->token = $completeSecret;
        $this->dateRequest = $datetime;
    }

    /**
     * Make a request to talenta server
     *
     */
    public function makeRequest()
    {
        // generate secret token
        $this->generateHmac();

        $method = $this->requestMethod;

        // make a request
        $response = Http::withHeaders([
                'Authorization' => $this->token,
                'Date' => $this->dateRequest
            ])
            ->acceptJson()
            ->$method($this->url, $this->payload);

        return $response->json();
    }
}