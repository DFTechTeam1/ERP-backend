<?php

namespace Modules\LineMessaging\Services;

use Illuminate\Support\Facades\Http;

class LineConnectionService {
    private $url;
    private $token;

    public function __construct()
    {
        $this->url = 'https://api.line.me/v2/bot';
        $this->token = config('linemessaging.line_token');
    }

    public function sendMessage(array $message, string $lineId)
    {
        $response = Http::withToken($this->token)
            ->post($this->url . '/message/push', [
                'to' => $lineId,
                'messages' => $message
            ]);
        
        $response = json_decode($response->body(), true);

        return $response;
    }

    /**
     * Register employee line ID
     *
     * @param any $event
     * @return void
     */
    protected function handleRegisterUser($event)
    {
        $textRaw = $event['message']['text'];
        $exp = explode(' ', $textRaw);

        // check userid format
        $split = str_split($exp[1]);

        if (strtolower($split[0]) != 'd' || strtolower($split[1]) != 'f') {
            // send wrong response message
            $wrongUserFormatMessage = [
                [
                    'type' => 'text',
                    'text' => 'Format user ID yang kamu ketik salah, coba lagi ya :)',
                ],
            ];
            $this->sendMessage($wrongUserFormatMessage, $event['source']['userId']);
        } else if (strtolower($split[0]) == 'd' || strtolower($split[1]) == 'f') {
            // check user id in database first
            $employee = \Modules\Hrd\Models\Employee::select('id')->whereRaw("LOWER(employee_id) = '" . strtolower($exp[1]) . "'")->first();

            if (!$employee) {
                $userNotFoundMsg = [
                    [
                        'type' => 'text',
                        'text' => 'User ID tidak ditemukan pada database, coba cek lagi ya dan masukan dengan benar',
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
                    \Modules\Hrd\Models\Employee::whereRaw("LOWER(employee_id) = '" . strtolower($exp[1]) . "'")
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

    protected function handleUpdateLineID($event)
    {
        
    }

    public function webhook(array $data)
    {
        if (isset($data['events'])) {
            foreach ($data['events'] as $event) {
                if ($event['type'] == 'message') {
                    $textRaw = $event['message']['text'];
                    $exp = explode(' ', $textRaw);

                    if (count($exp) == 2 && strtolower($exp[0]) == 'register') {
                        $this->handleRegisterUser($event);
                    }
                }
            }
        }

        return response()->json([
            'status' => 200,
            'message' => 'success',
        ], 200);
    }
}