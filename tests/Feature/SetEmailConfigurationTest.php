<?php

use Illuminate\Support\Facades\Cache;

/**
 * @param  array<string, string>  $values
 * @return array<int, array<string, string>>
 */
function emailSettingPayload(array $values): array
{
    return collect($values)
        ->map(fn (string $value, string $key) => [
            'code' => 'email',
            'key' => $key,
            'value' => $value,
        ])
        ->values()
        ->all();
}

it('rebuilds the smtp mailer when email settings change so long-running workers pick up new config', function () {
    Cache::forever('setting', emailSettingPayload([
        'email_host' => 'smtp.old.test',
        'email_port' => '587',
        'username' => 'old-user',
        'password' => 'old-pass',
        'sender_email' => 'old@test.com',
        'sender_name' => 'Old Sender',
    ]));

    setEmailConfiguration();
    $firstMailer = app('mail.manager')->mailer('smtp');

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.old.test');

    // Simulate the admin saving new email settings (storeEmail persists to DB
    // and rebuilds the 'setting' cache).
    Cache::forever('setting', emailSettingPayload([
        'email_host' => 'smtp.new.test',
        'email_port' => '2525',
        'username' => 'new-user',
        'password' => 'new-pass',
        'sender_email' => 'new@test.com',
        'sender_name' => 'New Sender',
    ]));

    setEmailConfiguration();
    $secondMailer = app('mail.manager')->mailer('smtp');

    // The mailer must be a fresh instance (purged), not the memoized one that
    // still carries the old SMTP transport.
    expect($secondMailer)->not->toBe($firstMailer);
    expect(config('mail.mailers.smtp.host'))->toBe('smtp.new.test');
});
