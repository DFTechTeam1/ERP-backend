<?php

namespace App\Http\Middleware;

use App\Services\EncryptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BearerTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->header('Authorization');

        $bearer = explode('Bearer ', $bearer);
        $service = new EncryptionService;
        $tokenRaw = $service->decrypt($bearer[1], env('SALT_KEY'));
        $decode = json_decode($tokenRaw);
        $request->headers->set('Authorization', 'Bearer '.$decode->token);

        return $next($request);
    }
}
