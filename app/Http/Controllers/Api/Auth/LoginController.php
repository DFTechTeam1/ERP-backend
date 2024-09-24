<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\ErrorCode\Code;
use DateTime;
use App\Exceptions\UserNotFound;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Login;
use App\Models\User;
use App\Repository\UserLoginHistoryRepository;
use App\Services\EncryptionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Nullix\CryptoJsAes\CryptoJsAes;

class LoginController extends Controller
{
    private $service;

    private $loginHistoryRepo;

    public function __construct()
    {
        $this->service = new EncryptionService;

        $this->loginHistoryRepo = new UserLoginHistoryRepository();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function login(Login $request)
    {
        try {
            $validated = $request->validated();

            $user = User::where('email', $validated['email'])
                ->with(['employee.position', 'roles'])
                ->first();

            if (!$user) {
                throw new UserNotFound(__('global.userNotFound'));
            }

            if (!$user->email_verified_at) {
                throw new UserNotFound(__('global.userNotActive'));
            }

            if (!Hash::check($validated['password'], $user->password)) {
                throw new UserNotFound(__('global.credentialDoesNotMatch'));
            }

            if (!isset($user->getRoleNames()[0])) {
                throw new \App\Exceptions\DoNotHaveAppPermission();
            }

            $role = $user->getRoleNames()[0];
            $roles = $user->roles;

            $roleId = null;
            if (count($roles) > 0) {
                $roleId = $roles[0]->id;
            }
            $permissions = count($user->getAllPermissions()) > 0 ? $user->getAllPermissions()->pluck('name')->toArray() : [];

            $token = $user->createToken($role, $permissions, now()->addHours(2));
            
            $menuService = new \App\Services\MenuService();
            $menus = $menuService->getMenus($user->getAllPermissions());

            $isProjectManager = false;

            $isEmployee = false;

            $isDirector = false;

            $isSuperUser = false;

            $positionAsDirectors = json_decode(getSettingByKey('position_as_directors'), true);

            $positionAsProjectManager = json_decode(getSettingByKey('position_as_project_manager'), true);

            $superUserRole = getSettingByKey('super_user_role');

            if ($roleId == $superUserRole) {
                $isSuperUser = true;
            }

            if (
                ($positionAsDirectors) &&
                ($user->employee) &&
                (in_array($user->employee->position->uid, $positionAsDirectors))
            ) {
                $isDirector = true;
            }

            if (
                ($positionAsProjectManager) &&
                ($user->employee) &&
                (in_array($user->employee->position->uid, $positionAsProjectManager))
            ) {
                $isProjectManager = true;
            }
            
            $emailShow = trim(
                strip_tags(
                    html_entity_decode(
                        $user->email, ENT_QUOTES, 'UTF-8')
                    )
                );
            if (strlen($user->email) > 15) {
                $emailShow = mb_substr($emailShow, 0, 15) . ' ...';
            } else {
                $emailShow = $user->email;
            }
            $user['email_show'] = $emailShow;

            $employee = \Modules\Hrd\Models\Employee::select("id")
                ->find($user->employee_id);

            $notifications = [];
            if ($employee) {
                $notifications = formatNotifications($employee->unreadNotifications->toArray());
            }

            $payload = [
                'token' => $token->plainTextToken,
                'exp' => date('Y-m-d H:i:s', strtotime($token->accessToken->expires_at)),
                'user' => $user,
                'permissions' => $permissions,
                'role' => $role,
                'menus' => $menus['data'],
                'role_id' => $roleId,
                'app_name' => getSettingByKey('app_name'),
                'board_start_calcualted' => getSettingByKey('board_start_calcualted'),
                'is_director' => $isDirector,
                'is_project_manager' => $isProjectManager,
                'is_super_user' => $isSuperUser,
                'notifications' => $notifications,
            ];

            $encryptedPayload = $this->service->encrypt(json_encode($payload), env('SALT_KEY'));

            // store histories
            $this->loginHistoryRepo->store([
                'user_id' => $user->id,
                'ip' => getClientIp(),
                'browser' => parseUserAgent(getUserAgentInfo())['browser'],
                'login_at' => Carbon::now(),
            ]);

            // store to cache for user device information
            \Illuminate\Support\Facades\Cache::rememberForever('userLogin' . $user->id, function () {
                return [
                    'ip' => getClientIp(),
                    'browser' => parseUserAgent(getUserAgentInfo()),
                ];
            });

            // TODO: further development
            // $encryptedPayload = $this->service->encrypt(json_encode($payload), env('SALT_KEY'));
    
            return apiResponse(
                generalResponse(
                    'Success',
                    false,
                    [
                        'token' => $encryptedPayload,
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
            \Illuminate\Support\Facades\Cache::forget('userLogin' . $user->id);

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
    
            if (!$user) {
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

            if (!$userData) {
                throw new \App\Exceptions\InvalidResetPasswordToken(__('global.invalidToken'));
            }

            // validate token claim
            $user = \App\Models\User::select('reset_password_token_claim')->where('email', $userData['email'])->first();
            if ($user->reset_password_token_claim) {
                throw new \App\Exceptions\ClaimedTokenResetPassword(__("global.tokenResetPasswordClaimed"));
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
                    __("global.resetPasswordSuccess"),
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
}
