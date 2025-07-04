<?php

namespace Modules\Telegram\Service\Webhook;

use App\Enums\Telegram\ChatStatus;
use App\Enums\Telegram\ChatType;
use App\Enums\Telegram\CommandList;
use App\Models\User;
use App\Services\Geocoding;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Modules\Hrd\Models\Employee;
use Modules\Nas\Services\NasService;
use Modules\Telegram\Enums\CallbackIdentity;
use Modules\Telegram\Enums\TelegramSessionKey;
use Modules\Telegram\Models\TelegramChatCommand;
use Modules\Telegram\Models\TelegramChatHistory;
use Modules\Telegram\Service\Action\MyTaskAction;

class Telegram
{
    private $senderId;

    private $senderName;

    private $chatId;

    private $message;

    private $service;

    public function __construct()
    {
        $this->service = new TelegramService;
    }

    protected function validatePermission(string $permission)
    {
        $employee = Employee::select('id')
            ->where('telegram_chat_id', $this->chatId)
            ->first();
        $user = User::where('employee_id', $employee->id)->first();

        if (! $user->hasPermissionTo($permission)) {
            $this->service->sendTextMessage($this->chatId, 'Wuushhh, KAMU TIDAK PUNYA AKSES COMMAND INI 😬');
            $out = false;
        } else {
            $out = true;
        }

        return $out;
    }

    protected function validateUser()
    {
        $employee = Employee::select('telegram_chat_id')
            ->where('telegram_chat_id', $this->chatId)
            ->first();

        if (! $employee && $this->message != '/connection') {
            $this->service->sendTextMessage($this->chatId, 'Wuuss, kamu siapa? aku tidak mengenalimu');
            $out = false;
        } else {
            $out = true;
        }

        return $out;
    }

    public function categorize(array $payload)
    {
        Log::debug('callback all', $payload);

        // Payload can be came from callback query
        if (isset($payload['message'])) {
            $this->senderId = $payload['message']['from']['id'];
            $this->senderName = $payload['message']['from']['username'] ?? 'Anonymous';
            $this->chatId = $payload['message']['chat']['id'];
        }

        try {
            if (
                (isset($payload['message'])) &&
                (isset($payload['message']['message_id']))
            ) {

                if (isset($payload['message']['text'])) {
                    $text = $payload['message']['text'];
                    $this->message = $text;

                    if (isset($payload['message']['entities'])) {
                        if ($payload['message']['entities'][0]['type'] == 'bot_command') {
                            $originalCommand = ltrim($text, '/');
                            $methodSuffix = ucfirst(snakeToCamel($originalCommand));
                            $method = 'handle'.$methodSuffix;

                            // reset if user change the topic
                            //                            $currentCommand = TelegramChatCommand::select('command')
                            //                                ->where('chat_id', $this->chatId)
                            //                                ->orderBy('id', 'desc')
                            //                                ->first();
                            //
                            //                            if (($currentCommand) && ($currentCommand->command != $originalCommand)) {
                            //                                TelegramChatCommand::where('chat_id', $this->chatId)
                            //                                    ->delete();
                            //                            }

                            //                            TelegramChatCommand::create([
                            //                                'command' => $originalCommand,
                            //                                'chat_id' => $this->chatId,
                            //                                'status' => 1
                            //                            ]);

                            if (method_exists($this, $method)) {
                                return $this->$method();
                            } else {
                                $this->service->sendTextMessage($this->chatId, 'Wah saya belum bisa memproses pesan kamu. Ulangi lagi yaa');
                            }
                        } elseif ($payload['message']['entities'][0]['type'] == 'url') {
                            $this->handleFreeText($payload);
                        }
                    } else {
                        $this->handleFreeText($payload);
                    }
                } elseif (isset($payload['message']['location'])) {
                    // handle geolocation from current command
                    if (
                        (isset($payload['message']['reply_to_message'])) &&
                        ($payload['message']['reply_to_message']['text'] == 'Kirim lokasimu')
                    ) {
                        // get location
                        $geo = new Geocoding;
                        $location = $geo->getPlaceName([
                            'lat' => $payload['message']['location']['latitude'],
                            'lon' => $payload['message']['location']['longitude'],
                        ]);

                        Log::debug('location', $location);

                        if (! empty($location)) {
                            $sendLocation = $this->service->sendTextMessage($this->chatId, 'Lokasimu berada di '.$location['street'], true);

                            Log::debug('sendLocation', $sendLocation);
                        }
                    }
                }

            } else {
                // THIS IS THE WAY TO HANDLE CALLBACK QUERY
                $callback = new Callback;
                $callback->handle($payload);
            }
        } catch (\Throwable $e) {
            Log::error($e);
            $this->service->sendTextMessage($this->chatId, 'Wah aku belum bisa memproses pesan mu nihhh', true);
        }
    }

    protected function haveContinueCommand(array $payload)
    {
        $data = TelegramChatCommand::select('command', 'current_function')
            ->where('chat_id', $this->chatId)
            ->orderBy('id', 'desc')
            ->first();

        if (! $data) {
            return false;
        }

        $action = new MyTaskAction;
        $action->handleContinueCommand($payload, $data);

        return true;
    }

    public function handleManageNas(array $payload = [])
    {
        if ($this->validateUser()) {
            // validate permission
            if ($this->validatePermission(permission: 'manage_nas')) {
                // send button options
                $this->service->sendButtonMessage($this->chatId, 'Silahkan pilih', [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Set aktif IP', 'callback_data' => 'idt='.CallbackIdentity::SetActiveIP->value],
                            ['text' => 'Set aktif root', 'callback_data' => 'idt='.CallbackIdentity::SetActiveRoot->value],
                            ['text' => 'Lihat Konfigurasi', 'callback_data' => 'idt='.CallbackIdentity::GetNasConfiguration->value],
                        ],
                        [
                            ['text' => 'Hapus Konfigurasi', 'callback_data' => 'idt='.CallbackIdentity::DeleteNasConfiguration->value],
                        ],
                    ],
                ]);
            }
        }
    }

    protected function registerNasIp(array $payload = [])
    {
        $message = $payload['message']['text'];

        $service = new NasService;
        $service->setIp(ip: $message);

        // delete current session
        destroyTelegramSession(chatId: $this->chatId, value: TelegramSessionKey::WaitingNasIp->value);

        // send success message
        $this->service->sendTextMessage(
            chatId: $this->chatId,
            message: 'Sip. IP untuk NAS aktif sekarang adalah '.$message
        );
    }

    protected function registerRootFolderName(array $payload = [])
    {
        $message = $payload['message']['text'];

        $service = new NasService;
        $service->setRoot(rootName: $message);

        // delete current session
        destroyTelegramSession(chatId: $this->chatId, value: TelegramSessionKey::WaitingRootFolderName->value);

        // send success message
        $this->service->sendTextMessage(
            chatId: $this->chatId,
            message: 'Sip. Root folder untuk NAS sudah di setting ke '.$message
        );
    }

    public function handleFreeText(array $payload = [])
    {
        try {
            $currentSession = getTelegramSession(chatId: $this->chatId);
            Log::debug('session', [$currentSession]);

            // check base on session
            if ($currentSession) {
                // get action from current session
                Log::debug('action callback', [TelegramSessionKey::getAction($currentSession)]);
                if ($action = TelegramSessionKey::getAction($currentSession)) {
                    if (method_exists($this, $action)) {
                        return $this->$action(payload: $payload);
                    }
                }
            } else {
                // first check the command continoues message
                // Then continue to other process
                if (! $this->haveContinueCommand($payload)) {
                    $current = TelegramChatHistory::selectRaw('id,chat_id,from_customer,bot_command,message,chat_type,is_closed')
                        ->where('chat_id', $this->chatId)
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($current->is_closed) {
                        return $this->service->sendTextMessage($this->chatId, 'Wah saya belum memahami apa yang kamu bicarakan. Coba mulai dari awal dengan command yang ada di menu ya 🙂');
                    }

                    // Only process message that came after bot message
                    if (! $current->from_customer) {
                        if ($current->chat_type == ChatType::FreeText->value) {
                            if ($current->bot_command == CommandList::Connection->value) {
                                return $this->handleConnectionReply($current);
                            } elseif ($current->bot_command == CommandList::MyTask->value) {
                                // THIS IS THE WAY TO HANDLE CALLBACK QUERY
                                $callback = new Callback;
                                $callback->handle($payload);
                            }
                        }
                    }
                }
            }

        } catch (\Throwable $th) {
            Log::error($th);
            $this->service->sendTextMessage($this->chatId, 'Wahh aku belum bisa memproses pesan mu nih', true);
        }

    }

    /**
     * In this function we assume that message will have 'Employee ID Format' something like DF001 DF00.....
     *
     * @return void
     */
    protected function handleConnectionReply(object $current)
    {
        // check employee format
        $text = strtolower(trim(str_replace(' ', '', $this->message)));

        if (! Str::startsWith($text, 'df')) {
            return $this->service->sendTextMessage($this->chatId, 'Format employee ID kamu salah. Masukan lagi dengan benar');
        } else {
            // check data
            $employee = Employee::selectRaw('id,telegram_chat_id,employee_id')
                ->where('employee_id', $text)
                ->first();
            if (! $employee) {
                return $this->service->sendTextMessage($this->chatId, 'Karyawan tidak ditemukan nih. Sepertin employee ID yang kamu masukan salah');
            }

            if ($employee->telegram_chat_id) {
                // closed
                TelegramChatHistory::where('id', $current->id)
                    ->update(['is_closed' => 1]);

                return $this->service->sendTextMessage($this->chatId, 'Akun mu sudah terhubung pada aplikasi');
            }

            //            TelegramChatHistory::where('id', $current->id)
            //                ->update(['is_closed' => 1]);
            //
            //            return $this->service->sendTextMessage($this->chatId, 'Selamat telegram kamu sudah terhubung dengan aplikasi. Selamat bekerja 😇');

            /**
             * Now send the login url to user.
             * This is to make sure he is the owner of the telegram
             */
            return $this->service->sendButtonMessage($this->chatId, 'Tingal 🤌 segini nih prosesnya. Klik tombol di bawah ya untuk validasi 🙂', [
                'inline_keyboard' => [
                    [
                        [
                            'login_url' => [
                                'url' => config('app.telegram_domain').'/api/telegram-login?employee_id='.$text.'&current_id='.$current->id,
                            ],
                            'text' => 'Cepat! Verifikasi Dirimu!',
                        ],
                    ],
                ],
            ]);
        }
    }

    protected function resetVariable()
    {
        $this->senderId = null;
        $this->senderName = null;
        $this->message = '';
    }

    public function handleHelp()
    {
        $this->service->sendTextMessage($this->chatId, 'Call 911');
    }

    public function handleStart()
    {
        $this->service->sendTextMessage($this->chatId, 'Halo selamat datang 🤙🤙');
    }

    public function handleAttendance()
    {
        if ($this->validateUser()) {
            $this->service->sendButtonMessage($this->chatId, 'Kirim lokasimu', [
                'keyboard' => [
                    [
                        ['text' => 'Lokasi', 'request_location' => true],
                    ],
                ],
                'is_persistent' => true,
                'one_time_keyboard' => true,
                'resize_keyboard' => true,
            ]);
        }
    }

    public function handleAwaitingTaskName(array $payload)
    {
        if ($this->validateUser()) {
            if ($this->validatePermission('add_task')) {
                $this->service->sendTextMessage($this->chatId, 'Pilih tim mu yang akan mengerjakan', true);
                $currentEmployee = Employee::select('id')->where('telegram_chat_id', $this->chatId)->first();
                $employees = Employee::selectRaw('id,nickname')
                    ->where('boss_id', $currentEmployee->id)
                    ->get();

                if (empty($employees)) {
                    // clear session
                    Session::forget('user_chat_state_'.$this->chatId);

                    return $this->service->sendTextMessage($this->chatId, 'Wah kamu masih belum mempunyai tim nih');
                }

                $keyboards = [];
                foreach ($employees as $employeeData) {
                    $keyboards[] = [
                        'text' => $employeeData->nickname,
                        'callback_data' => 'task_employee_'.$employeeData->id,
                    ];
                }

                $chunks = array_chunk($keyboards, 3);

                $this->service->sendButtonMessage($this->chatId, 'Pilih tim mu yang akan mengerjakan', [
                    'inline_keyboard' => [$chunks],
                ]);

                Session::put(
                    'user_chat_state_'.$this->chatId.'_task',
                    json_encode([
                        'task_name' => $payload['message']['text'],
                    ])
                );
            }
        }
    }

    public function handleCreateTask()
    {
        if ($this->validateUser()) {
            // validate permission
            if ($this->validatePermission('add_task')) {
                $this->service->sendTextMessage($this->chatId, 'Nama tugas baru kamu apa?', true);
                // store
                Session::put('user_chat_state_'.$this->chatId, 'awaiting_task_name');
            }
        }
    }

    public function handleMyTask()
    {
        if ($this->validateUser()) {
            // send more options
            $this->service->sendButtonMessage($this->chatId, 'Pilih beberapa opsi berikut', [
                'inline_keyboard' => [
                    [
                        ['text' => '1 minggu lagi harus selesai', 'callback_data' => 'idt='.CallbackIdentity::MyTask->value.'&f=deadline&tid='],
                    ],
                    [
                        ['text' => 'Berdasarkan event', 'callback_data' => 'idt='.CallbackIdentity::MyTask->value.'&f=event&tid='],
                    ],
                ],
            ]);
        }
    }

    public function handleMyProject()
    {
        // check the authentication
        if ($this->validateUser()) {
            // get all project year
            $projects = \Modules\Production\Models\Project::selectRaw('distinct year(project_date) as year')
                ->get();

            $years = collect($projects)->pluck('year')->toArray();

            // chunk to 2
            $chunks = array_chunk($years, 2);

            $outputYear = [];
            foreach ($chunks as $key => $chunk) {
                foreach ($chunk as $year) {
                    $outputYear[$key][] = [
                        'text' => $year,
                        'callback_data' => 'idt='.CallbackIdentity::MyProject->value.'&f=month&v='.$year.'&y='.$year.'&m=&pid=',
                    ];
                }
            }

            $this->service->sendButtonMessage($this->chatId, 'Pilih dulu beberapa pilihan dibawah ini ya', [
                'inline_keyboard' => $outputYear,
            ]);
        }
    }

    public function handleConnection(): void
    {
        // stopper
        // check current telegram chat id
        $checkId = Employee::select('id', 'nickname')
            ->where('telegram_chat_id', $this->chatId)
            ->first();

        if ($checkId) {
            $this->service->sendTextMessage($this->chatId, 'Haloo '.$checkId->nickname.', akunmu sudah terdaftar pada aplikasi nih');

            return;
        }

        TelegramChatHistory::create([
            'chat_id' => $this->chatId,
            'message' => CommandList::Connection->value,
            'status' => ChatStatus::Sent->value,
            'chat_type' => ChatType::BotCommand->value,
            'bot_command' => CommandList::Connection->value,
            'from_customer' => true,
            'topic' => 'connection',
        ]);

        $replyMessage = 'Halo '.$this->senderName.' Beritahu saya employee ID kamu';

        $newMessage = TelegramChatHistory::create([
            'chat_id' => $this->chatId,
            'message' => $replyMessage,
            'status' => ChatStatus::Processing->value,
            'chat_type' => ChatType::FreeText->value,
            'bot_command' => CommandList::Connection->value,
            'from_customer' => false,
        ]);

        $send = $this->service->sendTextMessage(
            $this->chatId,
            'Halo '.$this->senderName.' Beritahu saya employee ID kamu',
            true
        );

        TelegramChatHistory::where('id', $newMessage->id)
            ->update([
                'status' => $send['ok'] ? ChatStatus::Sent->value : ChatStatus::Failed->value,
            ]);

        $this->resetVariable();
    }
}
