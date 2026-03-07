<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class PartnerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $partnerUrl = config('app.partner_url');

        $sourceUrl = $request->header('X-Source');

        if (! $sourceUrl) {
            return response()->json([
                'message' => 'Partner not identified',
            ], 401);
        }

        if ($partnerUrl && $sourceUrl !== $partnerUrl) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Find partner user by email
        $partnerEmail = config('app.partner_email');
        $partnerPassword = config('app.partner_password');

        $user = User::where('email', $partnerEmail)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Partner user not found',
            ], 404);
        }

        // Verify password matches
        if (! Hash::check($partnerPassword, $user->password)) {
            return response()->json([
                'message' => 'Partner credentials invalid',
            ], 401);
        }

        // Set the authenticated user for this request
        Auth::setUser($user);

        return $next($request);
    }
}
