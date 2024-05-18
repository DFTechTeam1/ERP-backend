<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\ErrorCode\Code;
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

            $role = $user->getRoleNames()[0];
            $permissions = count($user->getAllPermissions()) > 0 ? $user->getAllPermissions()->pluck('name')->toArray() : [];

            $token = $user->createToken($role, $permissions, now()->addHours(2));

            $payload = [
                'token' => $token->plainTextToken,
                'exp' => date('Y-m-d H:i:s', strtotime($token->accessToken->expires_at)),
                'user' => $user,
                'permissions' => $permissions,
                'role' => $role,
            ];

            $encryptedPayload = $this->service->encrypt(json_encode($payload), env('SALT_KEY'));

            // store histories
            $this->loginHistoryRepo->store([
                'user_id' => $user->id,
                'login_at' => Carbon::now(),
            ]);

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
}
