<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
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
            'response_type' => 'required|string|in:code',
            'client_id' => 'required|string',
            'redirect_uri' => 'required|url',
            'code_challenge' => 'required|string',
            'code_challenge_method' => 'required|string|in:S256',
            'scope' => 'nullable|string',
            'state' => 'nullable|string',
        ]);

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
            'code_challenge' => $request->input('code_challenge'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $redirectUri = $request->redirect_uri
            .'?code='.$code
            .'&state='.$request->input('state', '');

        return redirect($redirectUri);
    }

    // Exchnage function code for JWT
    public function token(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'grant_type' => 'required|string|in:authorization_code',
                'code' => 'required|string',
                'code_verifier' => 'required|string',
            ]);
    
            $authCode = \App\Models\Mcp\OauthAuthCode::where('code', $request->code)
                ->notExpired()
                ->first();
    
            if (! $authCode) {
                return response()->json(['error' => 'Invalid or expired authorization code'], 400);
            }
    
            // Verify PKCE
            $computedChallenge = rtrim(
                strtr(base64_encode(hash('sha256', $request->code_verifier, true)), '+/', '-_'),
                '='
            );
    
            if (! hash_equals($authCode->code_challenge, $computedChallenge)) {
                return response()->json(['error' => 'Invalid code verifier'], 400);
            }
    
            $userId = $authCode->user_id;
            $scope = $authCode->scope;
            $authCode->delete();
    
            // Issue JWT using lcobucci/jwt
            $config = $this->jwtConfig();
            $now = new DateTimeImmutable;
    
            $token = $config->builder()
                ->issuedBy(config('app.url'))                               // iss — must match BACKEND_URL in MCP .env
                ->permittedFor(config('mcp.server_url'))                    // aud — must match MCP_SERVER_URL in MCP .env
                ->relatedTo((string) $userId)                               // sub — user ID from your DB
                ->identifiedBy((string) \Illuminate\Support\Str::uuid())    // jti
                ->issuedAt($now)                                            // iat
                ->expiresAt($now->modify('+1 hour'))                        // exp
                ->withClaim('scope', $scope)                                // custom scope claim
                ->getToken($config->signer(), $config->signingKey());

            DB::commit();
    
            return response()->json([
                'access_token' => $token->toString(),
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => $scope,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
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
        return response()->json([
            'client_id' => 'claude-' . \Illuminate\Support\Str::random(16),
            'client_name' => $request->client_name ?? "Claude",
            'redirect_uris' => $request->redirect_uris ?? [],
            'grant_types' => ['authorization_code'],
            'response_type' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'client_id_issued_at' => time(),
        ]);
    }
}
