<?php

namespace Modules\LineMessaging\Services;

use Illuminate\Support\Facades\Http;
use Vinkla\Hashids\Facades\Hashids;

class LineConnectionService
{
    private $url;

    private $token;

    private $bearerToken;

    public function __construct()
    {
        $this->url = 'https://api.line.me/v2/bot';
        $this->token = config('linemessaging.line_token');
    }

    public function sendMessage(array $message, string $lineId)
    {
        $response = Http::withToken($this->token)
            ->post($this->url.'/message/push', [
                'to' => $lineId,
                'messages' => $message,
            ]);

        $response = json_decode($response->body(), true);

        return $response;
    }

    /**
     * Register employee line ID
     *
     * @param  any  $event
     * @return void
     */
    protected function handleRegisterUser($event)
    {
        $textRaw = $event['message']['text'];
        $exp = explode(' ', $textRaw);

        logging('exp line', $exp);

        // validate format
        if (strtolower($exp[0]) != 'register') {
            $formatWrongMsg = [
                [
                    'type' => 'text',
                    'text' => 'Wah format pesanmu salah kawan :) Nice try',
                ],
            ];

            return $this->sendMessage($formatWrongMsg, $event['source']['userId']);
        }

        // check userid format
        $split = str_split($exp[1]);
        logging('count split line', $split);
        if (count($split) != 5) {
            $formatWrongMsg = [
                [
                    'type' => 'text',
                    'text' => 'Wah format pesanmu salah kawan :) Nice try',
                ],
            ];

            return $this->sendMessage($formatWrongMsg, $event['source']['userId']);
        }

        if (strtolower($split[0]) != 'd' || strtolower($split[1]) != 'f') {
            // send wrong response message
            $wrongUserFormatMessage = [
                [
                    'type' => 'text',
                    'text' => 'Format user ID yang kamu ketik salah, coba lagi ya :)',
                ],
            ];
            $this->sendMessage($wrongUserFormatMessage, $event['source']['userId']);
        } elseif (strtolower($split[0]) == 'd' || strtolower($split[1]) == 'f') {
            // check user id in database first
            $employee = \Modules\Hrd\Models\Employee::select('id')->whereRaw("LOWER(employee_id) = '".strtolower($exp[1])."' and status != ".\App\Enums\Employee\Status::Inactive->value)->first();

            if (! $employee) {
                $userNotFoundMsg = [
                    [
                        'type' => 'text',
                        'text' => 'User ID gak ada di database, cek lagi ya :)',
                    ],
                ];
                $this->sendMessage($userNotFoundMsg, $event['source']['userId']);
            } else { // REGISTER USER ID
                $checkLineId = \Modules\Hrd\Models\Employee::select('id')
                    ->where('line_id', $event['source']['userId'])
                    ->first();

                if ($checkLineId) {
                    $alreadRegisterMsg = [
                        [
                            'type' => 'text',
                            'text' => 'Akun kamu sudah terdaftar, silahkan melanjutkan pekerjaan kamu :)',
                        ],
                    ];
                    $this->sendMessage($alreadRegisterMsg, $event['source']['userId']);
                } else {
                    \Modules\Hrd\Models\Employee::whereRaw("LOWER(employee_id) = '".strtolower($exp[1])."'")
                        ->update(['line_id' => $event['source']['userId']]);

                    $successMsg = [
                        [
                            'type' => 'text',
                            'text' => 'Selamat! User id telah terdaftar pada akun anda. Anda akan menerima pesan jika ada notifikasi pada sistem ini. Selamat bekerja :)',
                        ],
                    ];

                    $this->sendMessage($successMsg, $event['source']['userId']);
                }

            }
        }
    }

    protected function handleUpdateLineID($event) {}

    public function webhook(array $data)
    {
        if (isset($data['events'])) {
            foreach ($data['events'] as $event) {
                if ($event['type'] == 'message') {
                    $textRaw = $event['message']['text'];
                    logging('text raw line', [$textRaw]);
                    $exp = explode(' ', $textRaw);

                    logging('exp raw line', $exp);

                    if (count($exp) == 2 && strtolower($exp[0]) == 'register') {
                        $this->handleRegisterUser($event);
                    }
                    if (count($exp) > 2 && strtolower($exp[0]) == 'register') {
                        $wrongUserFormatMessage = [
                            [
                                'type' => 'text',
                                'text' => 'Format user ID yang kamu ketik salah, coba lagi ya :)',
                            ],
                        ];
                        $this->sendMessage($wrongUserFormatMessage, $event['source']['userId']);
                    }

                    $containRejectRequestMember = str_contains($textRaw, 'alasan:');
                    if ($containRejectRequestMember) {
                        $this->handleRejectRequestMember($textRaw, $event['source']);
                    }

                } elseif ($event['type'] == 'postback') {
                    $textRaw = $event['postback']['data'];
                    $sender = $event['source']['userId'];

                    $containApproveRequestTeam = str_contains($textRaw, 'type=approveRequestTeam');
                    if ($containApproveRequestTeam) {
                        $this->handleApproveRequestMember($textRaw, $sender);
                    }

                    // handle approve task postback
                    $containApproveTask = str_contains($textRaw, 'approveTask=');
                    if ($containApproveTask) {
                        $this->handleApproveTask($textRaw);
                    }
                }
            }
        }

        return response()->json([
            'status' => 200,
            'message' => 'success',
        ], 200);
    }

    protected function handleApproveTask($text)
    {
        $main = explode('=', $text);

        if (isset($main[1])) {
            $decode = Hashids::decode($main[1]);

            logging('decode', $decode);

            if (count($decode) > 0) {
                $textRaw = explode(\App\Enums\CodeDivider::assignTaskJobDivider->value, $decode[0]);

                $taskId = $textRaw[0];
                $employeeId = $textRaw[1];
                $projectId = $textRaw[2];

                // based on task status
                $task = \Modules\Production\Models\ProjectTask::selectRaw('id,status,is_approved')
                    ->find($taskId);
                if (! $task->is_approved) {
                    $taskPic = \Modules\Production\Models\ProjectTaskPic::with(['task:id,name', 'employee'])
                        ->where('project_task_id', $taskId)
                        ->where('employee_id', $employeeId)
                        ->first();

                    if ($taskPic) {

                        \Illuminate\Support\Facades\Notification::send($taskPic->employee, new \Modules\Production\Notifications\WebhookApproveTaskNotification($taskPic));
                    }
                } else {

                }
            }
        }
    }

    protected function autoLogin($user)
    {
        $role = $user->getRoleNames()[0];
        $roles = $user->roles;
        $roleId = null;
        if (count($roles) > 0) {
            $roleId = $roles[0]->id;
        }
        $permissions = count($user->getAllPermissions()) > 0 ? $user->getAllPermissions()->pluck('name')->toArray() : [];

        $token = $user->createToken($role, $permissions, now()->addHours(2));

        $this->bearerToken = $token->plainTextToken;
    }

    protected function autoLogout($user)
    {
        $user->tokens()->delete();
    }

    protected function handleApproveRequestMember($text, $senderLineId)
    {
        // send waiting text
        $waitingMessage = [
            [
                'type' => 'text',
                'text' => 'Sistem sendang memproses permintaan kamu',
            ],
        ];
        $this->sendMessage($waitingMessage, $senderLineId);

        $exp = explode('type=approveRequestTeam&data=', $text);

        $data = json_decode($exp[1], true);

        if ($data) {
            // auto login
            $user = \App\Models\User::where('employee_id', $data['rid'])->first();
            if ($user) {
                $this->autoLogin($user);

                $transfer = \Modules\Production\Models\TransferTeamMember::find($data['tfid']);

                $resp = Http::withToken($this->bearerToken)
                    ->get(config('app.url')."/api/production/team-transfers/approve/{$transfer->uid}/line");

                logging('resp approve', [$resp]);

                $this->autoLogout($user);
            }
        }
    }

    protected function handleRejectRequestMember($text, $source)
    {
        $exp = explode("\nalasan:", $text);

        $reason = ltrim($exp[1]);

        $tokenExp = explode('tokenId=', $exp[0]);

        $token = $tokenExp[1];

        logging('token', [$token]);

        $token = Hashids::decode($token);

        logging('token decode', $token);

        if (count($token) > 0) {
            $divider = '107';

            $breakToken = explode($divider, (string) $token[0]);

            $transferId = array_pop($breakToken);

            $transfer = \Modules\Production\Models\TransferTeamMember::find($transferId);

            $user = \App\Models\User::where('employee_id', $transfer->request_to)->first();

            if ($user) {
                $this->autoLogin($user);

                $resp = Http::withTOken($this->bearerToken)
                    ->get(config('app.url')."/api/production/team-transfers/reject/{$transfer->uid}");

                logging('resp reject from line', [$resp]);

                $this->autoLogout($user);
            }
        }

    }
}
