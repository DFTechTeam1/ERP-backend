<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\ErrorCode\Code;
use App\Exceptions\ClaimedTokenResetPassword;
use App\Exceptions\ExpTokenResetPassword;
use App\Exceptions\InvalidResetPasswordToken;
use App\Exceptions\UserNotFound;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Login;
use App\Models\User;
use App\Models\UserEncryptedToken;
use App\Notifications\ForgotPasswordNotification;
use App\Repository\UserLoginHistoryRepository;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TokenService;
use App\Services\EncryptionService;
use App\Services\GeneralService;
use App\Services\UserService;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Vinkla\Hashids\Facades\Hashids;

class LoginController extends Controller
{
    private $service;

    private $loginHistoryRepo;

    private $userService;

    private TokenService $tokenService;

    private RefreshTokenService $refreshTokenService;

    public function __construct(
        UserService $userService,
        EncryptionService $encryptionService,
        UserLoginHistoryRepository $userLoginHistoryRepo,
        TokenService $tokenService,
        RefreshTokenService $refreshTokenService
    ) {
        $this->service = $encryptionService;

        $this->userService = $userService;

        $this->loginHistoryRepo = $userLoginHistoryRepo;

        $this->tokenService = $tokenService;

        $this->refreshTokenService = $refreshTokenService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Generate token to login into new interface
     */
    public function getDetailFromMigrate(string $code): JsonResponse
    {
        $decode = Hashids::decode($code);

        $userId = $decode[0] ?? 0;

        $data = UserEncryptedToken::where('user_id', $userId)->first();

        if (! $data) {
            // return error
        }

        $user = User::find($userId);

        $generatedToken = (new GeneralService)->generateAuthorizationToken(user: $user);

        return apiResponse(
            generalResponse(
                'success',
                false,
                [
                    'token' => $generatedToken['encryptedPayload'],
                    'reportingToken' => $generatedToken['reportingToken'],
                    'mEnc' => $generatedToken['mEnc'],
                    'pEnc' => $generatedToken['pEnc'],
                    'menus' => $generatedToken['menus'],
                    'main' => $generatedToken['mainToken'],
                ]
            )
        );
    }

    public function login(Login $request)
    {
        try {
            $validated = $request->validated();

            $generatedToken = $this->userService->login($validated);

            if (isset($generatedToken['error'])) {
                return apiResponse($generatedToken);
            }

            /**
             * Centralized auth (dual-issue, staged rollout): alongside the
             * legacy token fields, issue the new RS256 access token and a
             * rotating opaque refresh token (httpOnly cookie). The legacy
             * fields stay until every consumer verifies the new token locally.
             */
            $user = User::where('email', $validated['email'])->first();
            $remember = (bool) ($validated['remember_me'] ?? false);

            $accessToken = $this->tokenService->issueAccessToken($user);
            $refresh = $this->refreshTokenService->issue(
                user: $user,
                remember: $remember,
                userAgent: $request->userAgent(),
                ip: $request->ip(),
            );

            // Hit laravel event
            event(new \Illuminate\Auth\Events\Login('web', $user, $remember));

            return apiResponse(
                generalResponse(
                    'Success',
                    false,
                    [
                        'access_token' => $accessToken,
                        'token' => $generatedToken['encryptedPayload'],
                        'reportingToken' => $generatedToken['reportingToken'],
                        'mEnc' => $generatedToken['mEnc'],
                        'pEnc' => $generatedToken['pEnc'],
                        'menus' => $generatedToken['menus'],
                        'main' => $generatedToken['mainToken'],
                        'expressToken' => $generatedToken['expressToken'],
                    ],
                ),
            )->withCookie(
                $this->refreshTokenService->makeCookie($refresh['raw'], $remember)
            );
        } catch (\Throwable $th) {
            return apiResponse(
                generalResponse(
                    errorMessage($th),
                    true,
                    [],
                    Code::BadRequest->value,
                ),
            );
        }
    }

    /**
     * Sign out account
     *
     * @return void
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            // delete cache
            Cache::forget('userLogin'.$user->id);

            // Hit laravel event
            event(new \Illuminate\Auth\Events\Logout('web', $user));

            $user->tokens()->delete();

            // revoke the centralized refresh token and clear the cookie
            $raw = $request->cookie((string) config('jwt.refresh_cookie'))
                ?? $request->input('refresh_token');
            if ($raw) {
                $this->refreshTokenService->revoke($raw);
            }

            return apiResponse(
                generalResponse(
                    __('global.successLogout'),
                    false,
                ),
            )->withCookie($this->refreshTokenService->forgetCookie());
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function forgotPassword(Request $request)
    {
        try {
            $email = $request->email;

            $user = User::where('email', $email)->first();

            if (! $user) {
                throw new UserNotFound(__('global.userNotFound'));
            }

            setEmailConfiguration();

            $user->notify(new ForgotPasswordNotification($user));

            return apiResponse(
                generalResponse(
                    __('global.forgotPasswordLinkSent'),
                    false,
                )
            );
        } catch (\Throwable $th) {
            return apiResponse(errorResponse($th));
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $password = Hash::make($request->password);
            $userData = json_decode($this->service->decrypt($request->encrypted, config('app.saltKey')), true);

            if (! $userData) {
                throw new InvalidResetPasswordToken(__('global.invalidToken'));
            }

            // validate token claim
            $user = User::select('reset_password_token_claim')->where('email', $userData['email'])->first();
            if ($user->reset_password_token_claim) {
                throw new ClaimedTokenResetPassword(__('global.tokenResetPasswordClaimed'));
            }

            // validate token expiration
            $exp = new DateTime($userData['exp']);
            $now = new DateTime('now');
            $diff = date_diff($now, $exp);
            if ($diff->invert > 0) {
                throw new ExpTokenResetPassword(__('global.expToken'));
            }

            // update data
            User::where('email', $userData['email'])
                ->update([
                    'password' => $password,
                    'reset_password_token_claim' => true,
                ]);

            return apiResponse(
                generalResponse(
                    __('global.resetPasswordSuccess'),
                    false,
                    [
                        'user' => $userData,
                    ],
                ),
            );
        } catch (\Throwable $th) {
            return apiResponse(errorResponse($th));
        }
    }

    /**
     * Change password for authenticated user only
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            User::where('id', $user->id)
                ->update(['password' => Hash::make($request->password)]);

            return apiResponse(
                generalResponse(
                    'success',
                    false,
                    [
                        'user' => $user,
                    ]
                )
            );
        } catch (\Throwable $e) {
            return apiResponse(errorResponse($e));
        }
    }

    /**
     * Change password for selected user
     */
    public function userChangePassword(Request $request, string $userUid): JsonResponse
    {
        return apiResponse(
            $this->userService->userChangePassword($request->all(), $userUid)
        );
    }
}
