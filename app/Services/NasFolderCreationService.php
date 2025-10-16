<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NasFolderCreationService
{
    private string $ip;
    private string $sharedFolder;

    public function __construct(
        private readonly GeneralService $generalService,
    )
    {
        // get current host and shared folder
        $this->ip = config('app.env') == 'testing' ? 'ip' : $this->generalService->getSettingByKey(param: 'nas_current_ip');
        $this->sharedFolder = config('app.env') == 'testing' ? 'shared_folder' : $this->generalService->getSettingByKey(param: 'nas_current_root');
    }

    /**
     * Define the URL based on the environment.
     * @return string
     */
    public function getUrl(): string
    {
        return config('app.env') == 'local' || config('app.env') == 'staging'
            ? config('app.python_endpoint')
            : config('app.python_endpoint_prod');
    }

    /**
     * Send a request to the NAS service.
     * 
     * @param array $payload
     * @param string $type
     * @return bool
     */
    public function sendRequest(array $payload, string $type = 'create'): bool
    {
        $method = $type === 'delete' ? 'patch' : 'post';
        if ($type === 'create') {
            $payload['host'] = $this->ip;
            $payload['shared_folder'] = "/{$this->sharedFolder}";
        }

        $url = "/listener/nas/queue/{$type}";
        $fullUrl = $this->getUrl() . $url;

        
        try {
            $response = Http::withBody(
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                'application/json'
            )->$method($fullUrl);
            
            $logData = [
                'timestamp' => now()->toDateTimeString(),
                'method' => $method,
                'endpoint' => $fullUrl,
                'payload' => $payload,
                'status_code' => $response->status(),
                'response_body' => $response->json() ?? $response->body(),
            ];

            if ($response->successful()) {
                $logData['status'] = 'SUCCESS';
                $this->writeLog($logData);
            } else {
                $logData['status'] = 'FAILED';
                $logData['error_message'] = $response->body();
                $this->writeLog($logData);
            }
        } catch (\Exception $e) {
            $logData = [
                'timestamp' => now()->toDateTimeString(),
                'method' => $method,
                'endpoint' => $fullUrl,
                'payload' => $payload,
                'status' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ];
            $this->writeLog($logData);
        }

        // return success or not
        return isset($response) && $response->successful();
    }

    /**
     * Write log to file.
     * 
     * @param array $data
     * @return void
     */
    private function writeLog(array $data): void
    {
        $logFile = storage_path('logs/nas_service.log');
        $logEntry = "[{$data['timestamp']}] [{$data['status']}] {$data['method']} {$data['endpoint']}\n";
        $logEntry .= "Payload: " . json_encode($data['payload'], JSON_PRETTY_PRINT) . "\n";
        
        if (isset($data['status_code'])) {
            $logEntry .= "Status Code: {$data['status_code']}\n";
        }
        
        if (isset($data['response_body'])) {
            $logEntry .= "Response: " . json_encode($data['response_body'], JSON_PRETTY_PRINT) . "\n";
        }
        
        if (isset($data['error_message'])) {
            $logEntry .= "Error Message: {$data['error_message']}\n";
        }
        
        if (isset($data['exception_trace'])) {
            $logEntry .= "Exception Trace:\n{$data['exception_trace']}\n";
        }
        
        $logEntry .= str_repeat('-', 80) . "\n\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Clear the NAS service log file.
     * 
     * @return bool
     */
    public function clearLog(): bool
    {
        $logFile = storage_path('logs/nas_service.log');
        
        if (file_exists($logFile)) {
            return unlink($logFile);
        }
        
        return true;
    }
}