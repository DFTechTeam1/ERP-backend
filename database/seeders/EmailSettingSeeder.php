<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Company\Models\Setting;

class EmailSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::where('code', 'email')
            ->delete();

        $data = [
            [
                'key' => 'email_host',
                'value' => config('mail.mailers.smtp.host'),
                'code' => 'email',
            ],
            [
                'key' => 'email_port',
                'value' => config('mail.mailers.smtp.port'),
                'code' => 'email',
            ],
            [
                'key' => 'username',
                'value' => config('mail.mailers.smtp.username'),
                'code' => 'email',
            ],
            [
                'key' => 'password',
                'value' => config('mail.mailers.smtp.password'),
                'code' => 'email',
            ],
            [
                'key' => 'sender_email',
                'value' => 'df@gmail.com',
                'code' => 'email',
            ],
            [
                'key' => 'sender_name',
                'value' => 'DF Data Center',
                'code' => 'email',
            ],
        ];

        foreach ($data as $d) {
            Setting::create($d);
        }
    }
}
