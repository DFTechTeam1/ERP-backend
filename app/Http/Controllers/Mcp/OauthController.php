<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestMcpBearerToken;
use App\Models\OauthClient;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lcobucci\JWT\Configuration;

class OauthController extends Controller
{
    private function jwtConfig(): Configuration
    {
        return Configuration::forAsymmetricSigner(
            new \Lcobucci\JWT\Signer\Rsa\Sha256,
            \Lcobucci\JWT\Signer\Key\InMemory::file(storage_path('oauth/private.key')),
            \Lcobucci\JWT\Signer\Key\InMemory::file(storage_path('oauth/public.key')),
        );
    }

    // Show login form
    public function authorizeForm(Request $request)
    {
        $request->validate([
            'response_type' => 'required|in:code',
            'client_id' => 'required|string|exists:oauth_clients,client_id',
            'redirect_uri' => 'required|url',
            'code_challenge' => 'required|string',
            'code_challenge_method' => 'required|in:S256',
            'scope' => 'nullable|string',
            'state' => 'nullable|string',
        ]);

        $client = OauthClient::find($request->client_id);

        // Check redirect_uri is in registered list
        if (! in_array($request->redirect_uri, $client->redirect_uris)) {
            // Also allow if it starts with claude.ai domain
            if (! str_starts_with($request->redirect_uri, 'https://claude.ai')) {
                abort(400, 'Invalid redirect_uri');
            }
        }

        return view('oauth.authorize', [
            'redirect_uri' => $request->input('redirect_uri'),
            'scope' => $request->input('scope'),
            'state' => $request->input('state'),
            'client_id' => $request->input('client_id'),
            'code_challenge' => $request->input('code_challenge'),
            'code_challenge_method' => $request->input('code_challenge_method'),
        ]);
    }

    public function authorize(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'redirect_uri' => 'required|url',
            'code_challenge' => 'required|string',
            'code_challenge_method' => 'required|string|in:S256',
            'token_expiry' => 'required|string|in:5h,1d,1w,2w,1m,2m',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (! $user) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['email' => 'Invalid credentials']);
        }

        if ($user && ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['email' => 'Invalid password for this email']);
        }

        $code = \Illuminate\Support\Str::random(64);

        \App\Models\Mcp\OauthAuthCode::create([
            'code' => $code,
            'user_id' => $user->id,
            'scope' => $request->input('scope'),
            'token_expiry' => $request->input('token_expiry'),
            'code_challenge' => $request->input('code_challenge'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $redirectUri = $request->redirect_uri
            .'?code='.$code
            .'&state='.$request->input('state', '');

        return redirect($redirectUri);
    }

    public function generateStringToken(int $userId, string $scope, string $tokenExpiry = '1m'): string
    {
        $config = $this->jwtConfig();
        $now = new DateTimeImmutable;

        $token = $config->builder()
            ->issuedBy(config('app.url'))
            ->permittedFor(config('mcp.server_url'))
            ->relatedTo((string) $userId)
            ->identifiedBy((string) \Illuminate\Support\Str::uuid())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify($this->resolveExpiryModifier($tokenExpiry)))
            ->withClaim('scope', $scope)
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    private function resolveExpiryModifier(string $expiry): string
    {
        return match ($expiry) {
            '5h' => '+5 hours',
            '1d' => '+1 day',
            '1w' => '+1 week',
            '2w' => '+2 weeks',
            '2m' => '+2 months',
            default => '+1 month',
        };
    }

    public function generateMcpBearerToken(RequestMcpBearerToken $request)
    {
        try {
            $user = \App\Models\User::where('email', $request->email)->first();

            if (! $user) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            if ($user && ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            $tokenString = $this->generateStringToken($user->id, 'tasks:read tasks:write');

            return response()->json([
                'access_token' => $tokenString,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'tasks:read tasks:write',
            ]);
        } catch (\Throwable $th) {
            return apiResponse(errorResponse($th));
        }
    }

    // Exchnage function code for JWT
    public function token(Request $request)
    {
        DB::beginTransaction();
        try {
            logging('exchange token', $request->all());

            $request->validate([
                'grant_type' => 'required|string|in:authorization_code',
                'code' => 'required|string',
                'code_verifier' => 'required|string',
            ]);

            $authCode = \App\Models\Mcp\OauthAuthCode::where('code', $request->code)
                ->notExpired()
                ->first();

            logging('auth code data', $authCode->toArray());

            if (! $authCode) {
                return response()->json(['error' => 'Invalid or expired authorization code'], 400);
            }

            // Verify PKCE
            $computedChallenge = rtrim(
                strtr(base64_encode(hash('sha256', $request->code_verifier, true)), '+/', '-_'),
                '='
            );

            logging('computed challenge', [
                'computed' => $computedChallenge,
                'expected' => $authCode->code_challenge,
                'check hash' => hash_equals($authCode->code_challenge, $computedChallenge),
            ]);

            if (! hash_equals($authCode->code_challenge, $computedChallenge)) {
                return response()->json(['error' => 'Invalid code verifier'], 400);
            }

            $userId = $authCode->user_id;
            $scope = $authCode->scope;
            $authCode->delete();

            $tokenString = $this->generateStringToken($userId, $scope, $authCode->token_expiry);

            DB::commit();

            return response()->json([
                'access_token' => $tokenString,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => $scope,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            logging('token exchange error', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json(['error' => 'An error occurred while processing the token request'], 500);
        }
    }

    // GET /.well-known/oauth-authorization-server
    public function authorizationServerMetadata()
    {
        $base = config('app.url');

        return response()->json([
            'issuer' => $base,
            'authorization_endpoint' => "{$base}/oauth/authorize",
            'token_endpoint' => "{$base}/oauth/token",
            'jwks_uri' => "{$base}/.well-known/jwks.json",
            'registration_endpoint' => "{$base}/oauth/register",
            'scopes_supported' => ['tasks:read', 'tasks:write'],
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
        ]);
    }

    // GET /.well-known/jwks.json
    public function jwks()
    {
        $publicKeyContent = file_get_contents(storage_path('oauth/public.key'));
        $publicKey = openssl_pkey_get_public($publicKeyContent);
        $details = openssl_pkey_get_details($publicKey);
        $rsa = $details['rsa'];

        return response()->json([
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => 'key-1',
                'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
            ]],
        ]);
    }

    public function register(Request $request)
    {
        $clientId = 'claude-'.\Illuminate\Support\Str::random(16);

        $redirectUris = $request->redirect_uris ?? [
            'https://claude.ai/api/mcp/auth_callback',
        ];

        OauthClient::create([
            'client_id' => $clientId,
            'client_name' => $request->client_name ?? 'Claude',
            'redirect_uris' => $redirectUris,
            'client_id_issued_at' => now(),
        ]);

        return response()->json([
            'client_id' => $clientId,
            'client_name' => $request->client_name ?? 'Claude',
            'redirect_uris' => $redirectUris,
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],           // ← plural
            'token_endpoint_auth_method' => 'none',
            'client_id_issued_at' => time(),
            'client_secret_expires_at' => 0,                 // ← add this
        ], 201);
    }
}
