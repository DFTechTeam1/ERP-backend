<?php

namespace App\Http\Middleware;

use App\Models\Mcp\McpAccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogMcpAccess
{
    /**
     * Parameter keys that must never be persisted in plain text.
     *
     * @var array<int, string>
     */
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'client_secret',
        'authorization',
        'code_challenge',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $this->writeLog($request, $response, $start);

        return $response;
    }

    private function writeLog(Request $request, Response $response, float $start): void
    {
        try {
            $status = $response->getStatusCode();
            $user = Auth::user();

            McpAccessLog::create([
                'source' => 'laravel',
                'user_id' => $user?->getAuthIdentifier(),
                'user_email' => $user?->email,
                'user_name' => $user?->username,
                'method' => $request->getMethod(),
                'route_uri' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'status_code' => $status,
                'is_success' => $status >= 200 && $status < 300,
                'parameters' => $this->collectParameters($request),
                'response_message' => $this->extractResponseMessage($response),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'accessed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write MCP access log', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function collectParameters(Request $request): array
    {
        $params = array_merge($request->query(), $request->post());

        if (empty($params)) {
            return [];
        }

        foreach (array_keys($params) as $key) {
            if (in_array(strtolower((string) $key), $this->sensitiveKeys, true)) {
                $params[$key] = '[REDACTED]';
            }
        }

        return $params;
    }

    private function extractResponseMessage(Response $response): ?string
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            return mb_substr($decoded['message'], 0, 1000);
        }

        return mb_substr($content, 0, 1000);
    }
}
