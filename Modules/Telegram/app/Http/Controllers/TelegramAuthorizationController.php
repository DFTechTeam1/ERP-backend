<?php

namespace Modules\Telegram\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Hrd\Models\Employee;
use Modules\Telegram\Models\TelegramChatHistory;

class TelegramAuthorizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Log::debug('data tele: ', $request->all());
        $auth_data = $request->all();
        $check_hash = $auth_data['hash'];
        $employeeId = $auth_data['employee_id'];
        $currentChatHistoryId = $auth_data['current_id'];

        // check current telegram chat
        $employeeData = Employee::select('telegram_chat_id')
            ->where('employee_id', $employeeId)
            ->first();
        if ($employeeData->telegram_chat_id) {
            return view('telegram::telegram-error', [
                'errorMessage' => 'Whoopes!',
                'errorDescription' => "You're already registered"
            ]);
        }

        unset($auth_data['hash']);
        unset($auth_data['employee_id']);
        unset($auth_data['current_id']);
        $data_check_arr = [];
        foreach ($auth_data as $key => $value) {
            $data_check_arr[] = $key . '=' . $value;
        }
        sort($data_check_arr);

        $data_check_string = implode("\n", $data_check_arr);
        $secret_key = hash('sha256', config('app.telegram_bot_token'), true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);
        if (strcmp($hash, $check_hash) !== 0) {
            return view('telegram::telegram-error', [
                'errorMessage' => 'Oops!!',
                'errorDescription' => 'Data is NOT from Telegram. Who are you???'
            ]);
        }
        if ((time() - $auth_data['auth_date']) > 86400) {
            return view('telegram::telegram-error', [
                'errorMessage' => 'Data is outdated',
                'errorDescription' => 'Timeout for authentication. Please try again'
            ]);
        }

        Employee::where('employee_id', $employeeId)
            ->update(['telegram_chat_id' => $auth_data['id']]);

        // send a message
        $service = new TelegramService();
        $service->sendButtonMessage($auth_data['id'], 'Yeyy! Akunmu sudah terdaftar, silahkan menggunakn semua fitur yang ada di bot ini☄️ ', []);

        // close the conversation
        TelegramChatHistory::where('id', $currentChatHistoryId)
            ->update(['is_closed' => 1]);

        return view('telegram::telegram-auth', [
            'chat_id' => $auth_data['id'],
            'first_name' => $auth_data['first_name'],
            'username' => $auth_data['username'],
            'photo_url' => $auth_data['photo_url']
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('telegram::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('telegram::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('telegram::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
