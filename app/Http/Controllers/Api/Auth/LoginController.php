<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\ErrorCode\Code;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Login;
use App\Models\User;
use App\Models\UserEncryptedToken;
use App\Repository\UserLoginHistoryRepository;
use App\Services\EncryptionService;
use App\Services\UserService;
use DateTime;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;

class LoginController extends Controller
{
    private $service;

    private $loginHistoryRepo;

    private $userService;

    public function __construct(
        UserService $userService,
        EncryptionService $encryptionService,
        UserLoginHistoryRepository $userLoginHistoryRepo
    ) {
        $this->service = $encryptionService;

        $this->userService = $userService;

        $this->loginHistoryRepo = $userLoginHistoryRepo;
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
     *
     * @param string $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailFromMigrate(string $code): \Illuminate\Http\JsonResponse
    {
        $decode = Hashids::decode($code);

        $userId = $decode[0] ?? 0;

        $data = UserEncryptedToken::where('user_id', $userId)->first();

        if (! $data) {
            // return error
        }

        $user = \App\Models\User::find($userId);

        $generatedToken = (new \App\Services\GeneralService)->generateAuthorizationToken(user: $user);

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
                    'main' => $generatedToken['mainToken']
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

            // TODO: further development
            // $encryptedPayload = $this->service->encrypt(json_encode($payload), env('SALT_KEY'));

            return apiResponse(
                generalResponse(
                    'Success',
                    false,
                    [
                        'token' => $generatedToken['encryptedPayload'],
                        'reportingToken' => $generatedToken['reportingToken'],
                        'mEnc' => $generatedToken['mEnc'],
                        'pEnc' => $generatedToken['pEnc'],
                        'menus' => $generatedToken['menus'],
                        'main' => $generatedToken['mainToken']
                    ],
                ),
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
            \Illuminate\Support\Facades\Cache::forget('userLogin'.$user->id);

            $user->tokens()->delete();

            return apiResponse(
                generalResponse(
                    __('global.successLogout'),
                    false,
                ),
            );
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

            $user = \App\Models\User::where('email', $email)->first();

            if (! $user) {
                throw new \App\Exceptions\UserNotFound(__('global.userNotFound'));
            }

            setEmailConfiguration();

            $user->notify(new \App\Notifications\ForgotPasswordNotification($user));

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
            $password = \Illuminate\Support\Facades\Hash::make($request->password);
            $userData = json_decode($this->service->decrypt($request->encrypted, config('app.saltKey')), true);

            if (! $userData) {
                throw new \App\Exceptions\InvalidResetPasswordToken(__('global.invalidToken'));
            }

            // validate token claim
            $user = \App\Models\User::select('reset_password_token_claim')->where('email', $userData['email'])->first();
            if ($user->reset_password_token_claim) {
                throw new \App\Exceptions\ClaimedTokenResetPassword(__('global.tokenResetPasswordClaimed'));
            }

            // validate token expiration
            $exp = new DateTime($userData['exp']);
            $now = new DateTime('now');
            $diff = date_diff($now, $exp);
            if ($diff->invert > 0) {
                throw new \App\Exceptions\ExpTokenResetPassword(__('global.expToken'));
            }

            // update data
            \App\Models\User::where('email', $userData['email'])
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
    public function changePassword(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = auth()->user();

            \App\Models\User::where('id', $user->id)
                ->update(['password' => \Illuminate\Support\Facades\Hash::make($request->password)]);

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
    public function userChangePassword(Request $request, string $userUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse(
            $this->userService->userChangePassword($request->all(), $userUid)
        );
    }
}
