<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalServiceMiddleware
{
    /**
     * Maximum allowed clock skew in seconds to prevent replay attacks.
     */
    private const TIMESTAMP_TOLERANCE_SECONDS = 30;

    /**
     * Handle an incoming request.
     *
     * HMAC verification flow:
     * 1. Client builds a signing string: "{METHOD}\n{PATH}\n{BODY}\n{TIMESTAMP}"
     * 2. Client signs it with HMAC-SHA256 using the shared INTERNAL_SERVICE_SECRET
     * 3. Client sends X-Timestamp and X-Signature headers
     * 4. We verify the signature and check the timestamp is within 30 seconds
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('app.internal_service_secret');

        if (! $secret) {
            return response()->json([
                'message' => 'Internal service secret is not configured.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        $serviceName = $request->header('X-Service-Name');

        if (! $timestamp || ! $signature || ! $serviceName) {
            return response()->json([
                'message' => 'Missing required headers: X-Timestamp, X-Signature, X-Service-Name.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->isTimestampValid((int) $timestamp)) {
            return response()->json([
                'message' => 'Request timestamp is expired or invalid.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $signingString = $this->buildSigningString($request, $timestamp);
        $expectedSignature = hash_hmac('sha256', $signingString, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'message' => 'Invalid signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    /**
     * Check that the timestamp is within the allowed tolerance window.
     */
    private function isTimestampValid(int $timestamp): bool
    {
        $now = time();

        return abs($now - $timestamp) <= self::TIMESTAMP_TOLERANCE_SECONDS;
    }

    /**
     * Build the canonical signing string from the request.
     *
     * Both sides must normalize the body to minified JSON so whitespace
     * differences between the Bruno editor and the actual HTTP body don't
     * cause a signature mismatch.
     *
     * Format: "{METHOD}\n{PATH}\n{MINIFIED_JSON_BODY}\n{TIMESTAMP}"
     */
    private function buildSigningString(Request $request, string $timestamp): string
    {
        $rawBody = $request->getContent();
        $normalizedBody = $rawBody ? json_encode(json_decode($rawBody)) : '';

        return implode("\n", [
            strtoupper($request->method()),
            $request->path(),
            $normalizedBody,
            $timestamp,
        ]);
    }
}
