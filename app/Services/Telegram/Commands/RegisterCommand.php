<?php

namespace App\Services\Telegram\Commands;

use Modules\Hrd\Models\Employee;
use Telegram\Bot\Commands\Command;

class RegisterCommand extends Command
{
    protected string $name = 'register';

    protected string $description = 'Start Command to get you started';

    protected string $pattern = '{employee_id}';

    public function handle()
    {
        $employeeId = $this->argument('employee_id', '');

        $employee = Employee::select('id')
            ->where('employee_id', $employeeId)
            ->first();

        if (! $employee) {
            $this->replyWithMessage([
                'text' => 'Data karyawan tidak ditemukan. Masukan employee_id dengan benar ya 🙂',
            ]);
        } else {
            $chatId = $this->getUpdate()->getMessage()->getChat()->getId();
            Employee::where('employee_id', $employee)
                ->update(['telegram_chat_id' => $chatId]);

            $this->replyWithMessage([
                'text' => 'Register berhasil. Kamu akan menerima notifikasi pekerjaan mu melalui chat room ini 🫰.',
                'reply_markup' => json_encode(['remove_keyboard' => true]),
            ]);
        }

    }
}
